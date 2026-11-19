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

        if data:
            df = pd.DataFrame(data)
            filename = os.path.join(SAVE_PATH, f"{league_key}_table.csv")
            df.to_csv(filename, index=False)
            print(f"✅ {league_key} შენახულია ({len(data)} გუნდი).")
        
    except Exception as e:
        print(f"❌ შეცდომა {league_key}-ზე: {e}")

if __name__ == "__main__":
    if os.name != "nt":
        os.system("pkill -9 google-chrome 2>/dev/null")
    
    driver = init_driver()
    try:
        for key, url in LEAGUES.items():
            scrape_league(driver, key, url)
            time.sleep(5)
    finally:
        driver.quit()
        print("🏁 პროცესი დასრულდა.")
