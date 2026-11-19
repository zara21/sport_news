import time
import re
import os
import sys
import pandas as pd
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

def slugify(text):
    text = str(text).lower()
    text = re.sub(r'\s+', '-', text)
    text = re.sub(r'[^a-z0-9\-]', '', text)
    return text

def main():
    options = Options()
    options.add_argument("--headless")
    driver = webdriver.Firefox(options=options)
    
    url = "https://www.flashscore.com/" 
    
    try:
        print(f"🌐 გვერდი იტვირთება: {url}")
        driver.get(url)
        
        # ველოდებით მატჩების გამოჩენას
        wait = WebDriverWait(driver, 20)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".event__match")))
        
        # ვასკროლავთ, რომ ლოგოები ჩაიტვირთოს
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight/2);")
        time.sleep(2)

        matches_data = []
        
        # 1. ვიღებთ ყველა ელემენტს (ლიგის ჰედერებს და მატჩებს)
        # რათა გავიგოთ რომელი მატჩი რომელ ლიგას ეკუთვნის
        elements = driver.find_elements(By.CSS_SELECTOR, ".headerLeague__wrapper, .event__match")
        
        current_league = "Unknown Tournament"

        for el in elements:
            # თუ ელემენტი ლიგის ჰედერია, ვაახლებთ მიმდინარე ლიგის სახელს
            if "headerLeague__wrapper" in el.get_attribute("class"):
                try:
                    league_name = el.find_element(By.CLASS_NAME, "headerLeague__title-text").text.strip()
                    category = el.find_element(By.CLASS_NAME, "headerLeague__category-text").text.strip()
                    current_league = f"{category}: {league_name}"
                except:
                    continue
                continue
            
            # თუ ელემენტი მატჩია
            try:
                # მატჩის ID
                full_id = el.get_attribute("id")
                match_id = full_id.replace("g_1_", "")
                
                # გუნდები და ლოგოები (ახალი კლასებით)
                home_el = el.find_element(By.CLASS_NAME, "event__homeParticipant")
                away_el = el.find_element(By.CLASS_NAME, "event__awayParticipant")
                
                home_team = home_el.find_element(By.CLASS_NAME, "wcl-name_jjfMf").text.strip()
                away_team = away_el.find_element(By.CLASS_NAME, "wcl-name_jjfMf").text.strip()
                
                home_logo = home_el.find_element(By.TAG_NAME, "img").get_attribute("src")
                away_logo = away_el.find_element(By.TAG_NAME, "img").get_attribute("src")

                # გოლები
                try:
                    home_goals = el.find_element(By.CLASS_NAME, "event__score--home").text.strip()
                    away_goals = el.find_element(By.CLASS_NAME, "event__score--away").text.strip()
                    # თუ გოლი არ არის (მაგ: ტირეა), ვინახავთ როგორც None ბაზისთვის
                    if home_goals == "-": home_goals = None
                    if away_goals == "-": away_goals = None
                except:
                    home_goals, away_goals = None, None

                # დრო და სტატუსი
                try:
                    match_time = el.find_element(By.CLASS_NAME, "event__time").text.strip()
                except:
                    match_time = ""
                
                try:
                    # სტატუსი შეიძლება იყოს .event__stage ან .event__stage--pkv
                    match_status = el.find_element(By.CSS_SELECTOR, ".event__stage, .event__stage--pkv").text.strip()
                except:
                    match_status = ""

                live_link = f"https://www.flashscore.com/match/{match_id}/#/match-summary"
                slug = f"{slugify(home_team)}-vs-{slugify(away_team)}"

                matches_data.append({
                    "tournament_name": current_league,
                    "match_time": match_time,
                    "home_team": home_team,
                    "home_logo": home_logo,
                    "away_team": away_team,
                    "away_logo": away_logo,
                    "home_goals": home_goals,
                    "away_goals": away_goals,
                    "match_status": match_status,
                    "Live Link": live_link,
                    "Slug": slug,
                    "iframe-live": ""
                })
            except Exception as e:
                continue

        # შენახვა
        df = pd.DataFrame(matches_data)
        save_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "flashscore_matches_with_logos_and_links.csv")
        df.to_csv(save_path, sep='\t', index=False, encoding='utf-8')
        print(f"✅ წარმატება! {len(df)} მატჩი ჩაიწერა ფაილში.")

    except Exception as e:
        print(f"❌ კრიტიკული შეცდომა: {e}")
    finally:
        driver.quit()

if __name__ == "__main__":
    main()
