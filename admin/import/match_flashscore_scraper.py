import os
import re
import sys
import time

import pandas as pd
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

URL = "https://www.flashscore.com/"
SAVE_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "flashscore_matches_with_logos_and_links.csv")
TIME_RE = re.compile(r"\b(\d{1,2}:\d{2})\b")


def slugify(text):
    text = str(text).lower()
    text = re.sub(r"\s+", "-", text)
    text = re.sub(r"[^a-z0-9\-]", "", text)
    return text


def first_text(element, selectors):
    """აბრუნებს პირველი მოძებნილი ელემენტის ტექსტს (სტაბილური selector-ები, hash-იანი კლასების გარეშე)."""
    for selector in selectors:
        found = element.find_elements(By.CSS_SELECTOR, selector)
        if found:
            text = found[0].text.strip()
            if text:
                return text
    return ""


def expand_collapsed_leagues(driver):
    """
    Flashscore-ზე ნაწილი ლიგა დაკეცილია ("display matches (N)") და მათი მატჩები DOM-ში არ არის.
    ვაჭერთ ყველა ასეთ ღილაკს და ველოდებით, სანამ მატჩების რაოდენობა გაიზრდება.
    """
    script = """
        const leaves = [...document.querySelectorAll('body *')].filter(
            (el) => el.children.length === 0 && /^\\s*display matches\\s*\\(\\d+\\)\\s*$/i.test(el.textContent || '')
        );
        let expected = 0;
        for (const leaf of leaves) {
            const count = parseInt((leaf.textContent.match(/\\((\\d+)\\)/) || [0, 0])[1], 10);
            expected += count;
            const target = leaf.closest('button, a, [role="button"]') || leaf.parentElement || leaf;
            target.click();
        }
        return [leaves.length, expected];
    """
    total_clicked, total_expected = 0, 0
    for _ in range(3):
        before = len(driver.find_elements(By.CSS_SELECTOR, ".event__match"))
        clicked, expected = driver.execute_script(script)
        if not clicked:
            break
        total_clicked += clicked
        total_expected += expected
        deadline = time.time() + 15
        while time.time() < deadline:
            if len(driver.find_elements(By.CSS_SELECTOR, ".event__match")) >= before + expected:
                break
            time.sleep(0.5)
    return total_clicked, total_expected


def parse_match(el, current_league):
    match_id = (el.get_attribute("id") or "").replace("g_1_", "")
    home_el = el.find_element(By.CSS_SELECTOR, ".event__homeParticipant")
    away_el = el.find_element(By.CSS_SELECTOR, ".event__awayParticipant")

    home_team = first_text(home_el, ['[class*="wcl-name"]', '[data-testid*="name"]', "span", "strong"]) or home_el.text.strip()
    away_team = first_text(away_el, ['[class*="wcl-name"]', '[data-testid*="name"]', "span", "strong"]) or away_el.text.strip()
    if not home_team or not away_team or not match_id:
        return None

    def logo(participant):
        imgs = participant.find_elements(By.TAG_NAME, "img")
        return (imgs[0].get_attribute("src") or "") if imgs else ""

    home_goals = first_text(el, [".event__score--home"]) or None
    away_goals = first_text(el, [".event__score--away"]) or None
    if home_goals == "-":
        home_goals = None
    if away_goals == "-":
        away_goals = None

    # FRO ("Final result only") — მატჩი მხოლოდ საბოლოო შედეგით: არ ვიღებთ
    raw_time = first_text(el, [".event__time"])
    row_tokens = set(re.split(r"\s+", (raw_time + "\n" + (el.text or "")).upper()))
    if "FRO" in row_tokens:
        return "FRO"

    # დრო: "12:00" (ზედმეტი ნიშნები იჭრება)
    time_match = TIME_RE.search(raw_time)
    match_time = time_match.group(1) if time_match else ""

    match_status = first_text(el, [".event__stage--block", ".event__stage"]).replace("\n", " ").strip()
    match_status = re.sub(r"\s+", " ", match_status)

    return {
        "tournament_name": current_league,
        "match_time": match_time,
        "home_team": home_team,
        "home_logo": logo(home_el),
        "away_team": away_team,
        "away_logo": logo(away_el),
        "home_goals": home_goals,
        "away_goals": away_goals,
        "match_status": match_status,
        "Live Link": f"https://www.flashscore.com/match/{match_id}/#/match-summary",
        "Slug": f"{slugify(home_team)}-vs-{slugify(away_team)}",
        "iframe-live": "",
    }


def main():
    options = Options()
    options.add_argument("--headless")
    driver = webdriver.Firefox(options=options)

    try:
        print(f"🌐 გვერდი იტვირთება: {URL}")
        driver.get(URL)
        WebDriverWait(driver, 30).until(EC.presence_of_element_located((By.CSS_SELECTOR, ".event__match")))
        time.sleep(2)

        initial = len(driver.find_elements(By.CSS_SELECTOR, ".event__match"))
        clicked, expected = expand_collapsed_leagues(driver)
        visible = len(driver.find_elements(By.CSS_SELECTOR, ".event__match"))
        print(f"ℹ️ თავიდან {initial} მატჩი; გაიშალა {clicked} ლიგა (+{expected}); სულ DOM-ში {visible}")

        # ლოგოები lazy-load-ით იტვირთება
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(1.5)

        matches_data = []
        skipped = 0
        skipped_fro = 0
        current_league = "Unknown Tournament"
        for el in driver.find_elements(By.CSS_SELECTOR, ".headerLeague__wrapper, .event__match"):
            classes = el.get_attribute("class") or ""
            if "headerLeague__wrapper" in classes:
                category = first_text(el, [".headerLeague__category-text"])
                league = first_text(el, [".headerLeague__title-text"])
                if league:
                    current_league = f"{category}: {league}" if category else league
                continue
            try:
                row = parse_match(el, current_league)
            except Exception:
                row = None
            if row == "FRO":
                skipped_fro += 1
            elif row:
                matches_data.append(row)
            else:
                skipped += 1

        if skipped_fro:
            print(f"ℹ️ გამოტოვდა {skipped_fro} FRO მატჩი (მხოლოდ საბოლოო შედეგი).")

        if not matches_data:
            print("❌ მატჩები ვერ მოიძებნა — CSV არ გადაიწერა (Flashscore-ის სტრუქტურა შეიძლება შეიცვალა).")
            return 1

        if skipped:
            print(f"⚠️ {skipped} მატჩის წაკითხვა ვერ მოხერხდა.")

        # ჯერ დროებით ფაილში, მერე ატომურად ჩანაცვლება — იმპორტი ნახევრად ჩაწერილ ფაილს არ წაიკითხავს
        df = pd.DataFrame(matches_data)
        tmp_path = SAVE_PATH + ".tmp"
        df.to_csv(tmp_path, sep="\t", index=False, encoding="utf-8")
        os.replace(tmp_path, SAVE_PATH)
        leagues = df["tournament_name"].nunique()
        print(f"✅ წარმატება! {len(df)} მატჩი ({leagues} ტურნირი) ჩაიწერა ფაილში.")
        return 0

    except Exception as e:
        print(f"❌ კრიტიკული შეცდომა: {e}")
        return 1
    finally:
        driver.quit()


if __name__ == "__main__":
    sys.exit(main())
