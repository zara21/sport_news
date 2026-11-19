import os
import sys
import time
import pandas as pd
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from webdriver_manager.chrome import ChromeDriverManager

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# ლიგების სია შენი ბაზის მიხედვით
LEAGUES = {
    'premier_league': 'https://www.flashscore.ge/football/england/premier-league/standings/',
    'la_liga': 'https://www.flashscore.ge/football/spain/laliga/standings/',
    'serie_a': 'https://www.flashscore.ge/football/italy/serie-a/standings/',
    'bundesliga': 'https://www.flashscore.ge/football/germany/bundesliga/standings/',
    'ligue_1': 'https://www.flashscore.ge/football/france/ligue-1/standings/'
}

SAVE_PATH = os.path.dirname(os.path.abspath(__file__))

def init_driver():
    chrome_options = Options()
    chrome_options.add_argument("--headless=new")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--disable-gpu")
    if os.name != "nt" and os.path.exists("/usr/bin/google-chrome"):
        chrome_options.binary_location = "/usr/bin/google-chrome"
    
    # RAM ოპტიმიზაცია
    prefs = {"profile.managed_default_content_settings.images": 2}
    chrome_options.add_experimental_option("prefs", prefs)

    service = Service(ChromeDriverManager().install())
    driver = webdriver.Chrome(service=service, options=chrome_options)
    driver.set_page_load_timeout(150)
    return driver

EXPECTED_TEAMS = {"premier_league": 20, "la_liga": 20, "serie_a": 20, "bundesliga": 18, "ligue_1": 18}


def validate_table(data):
    """ამოწმებს, რომ ცხრილი სრული და შინაგანად თანმიმდევრულია, სანამ ძველ CSV-ს გადავაწერთ."""
    problems = []
    if not data:
        return ["ცხრილი ცარიელია"]
    league = data[0]["league_name"]
    if len(data) != EXPECTED_TEAMS.get(league, len(data)):
        problems.append(f"გუნდების რაოდენობა {len(data)}, მოსალოდნელია {EXPECTED_TEAMS[league]}")
    if [row["position"] for row in data] != list(range(1, len(data) + 1)):
        problems.append("პოზიციები არ არის 1..N")
    for row in data:
        if row["wins"] + row["draws"] + row["losses"] != row["played"]:
            problems.append(f"{row['team']}: W+D+L != P")
        if 3 * row["wins"] + row["draws"] != row["points"]:
            problems.append(f"{row['team']}: ქულები არ ემთხვევა")
    return problems


def scrape_league(driver, league_key, url):
    print(f"⏳ მუშავდება: {league_key}...")
    try:
        driver.get(url)
        wait = WebDriverWait(driver, 60)
        # ველოდებით სტრიქონების გამოჩენას
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "div.ui-table__row")))
        
        rows = driver.find_elements(By.CSS_SELECTOR, "div.ui-table__row")
        data = []

        for row in rows:
            try:
                # პოზიცია
                pos_text = row.find_element(By.CSS_SELECTOR, ".tableCellRank").text.replace('.', '').strip()
                # გუნდი
                team_name = row.find_element(By.CSS_SELECTOR, ".tableCellParticipant__name").text.strip()
                
                # სტატისტიკა (P, W, D, L, G, GD, Pts)
                # HTML-ში ისინი არიან span.table__cell--value კლასით
                stats = row.find_elements(By.CSS_SELECTOR, "span.table__cell--value")
                
                if len(stats) >= 7:
                    data.append({
                        "league_name": league_key,
                        "position": int(pos_text),
                        "team": team_name,
                        "played": int(stats[0].text.strip()),
                        "wins": int(stats[1].text.strip()),
                        "draws": int(stats[2].text.strip()),
                        "losses": int(stats[3].text.strip()),
                        "score": stats[4].text.strip(),       # მაგ: "61:22"
                        "goal_diff": int(stats[5].text.strip()),
                        "points": int(stats[6].text.strip())
                    })
            except Exception as e:
                continue

        problems = validate_table(data)
        if problems:
            print(f"❌ {league_key}: მონაცემები არ შეინახა — {'; '.join(problems[:3])}")
            return False

        df = pd.DataFrame(data)
        filename = os.path.join(SAVE_PATH, f"{league_key}_table.csv")
        # ჯერ დროებით ფაილში, მერე ატომურად ჩანაცვლება
        df.to_csv(filename + ".tmp", index=False)
        os.replace(filename + ".tmp", filename)
        print(f"✅ {league_key} შენახულია ({len(data)} გუნდი).")
        return True

    except Exception as e:
        print(f"❌ შეცდომა {league_key}-ზე: {e}")
        return False

if __name__ == "__main__":
    if os.name != "nt":
        os.system("pkill -9 google-chrome 2>/dev/null")
    
    driver = init_driver()
    failed = []
    try:
        for key, url in LEAGUES.items():
            if not scrape_league(driver, key, url):
                failed.append(key)
            time.sleep(5)
    finally:
        driver.quit()
        print("🏁 პროცესი დასრულდა.")

    if failed:
        print(f"⚠️ ვერ განახლდა: {', '.join(failed)} (ამ ლიგებისთვის ძველი CSV დარჩა)")
    # ყველა ლიგა ჩავარდა => error კოდი, რომ სინქრონიზაციამ ეს შეცდომად აღრიცხოს
    sys.exit(1 if len(failed) == len(LEAGUES) else 0)
