import pandas as pd
import mysql.connector
import os
import sys
import numpy as np
import re

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# მონაცემთა ბაზის კონფიგურაცია
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'auth_system'
}

CSV_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'flashscore_matches_with_logos_and_links.csv')

def is_valid_score(val):
    """ამოწმებს არის თუ არა მნიშვნელობა ვალიდური ციფრი"""
    try:
        if pd.isna(val) or str(val).strip() == "" or str(val).strip() == "-":
            return None
        return int(float(val))
    except (ValueError, TypeError):
        return None

def clean_match_time(val):
    if pd.isna(val):
        return ""
    match = re.search(r"\b\d{1,2}:\d{2}\b", str(val))
    return match.group(0) if match else ""

def clean_match_status(val):
    if pd.isna(val):
        return ""
    status = str(val).strip()
    # Flashscore sometimes leaks country/flag labels into the stage field.
    if status in {"FRO"}:
        return ""
    return status

try:
    if not os.path.exists(CSV_FILE):
        print(f"❌ CSV ფაილი ვერ მოიძებნა: {CSV_FILE}")
        exit()

    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()

    print("🧹 ცხრილის გასუფთავება...")
    cursor.execute("TRUNCATE TABLE matches")

    # წაკითხვა Tab-ით დაშორებული ფორმატით
    df = pd.read_csv(CSV_FILE, sep='\t')

    print(f"🔄 ბაზაში იტვირთება {len(df)} მატჩი...")

    sql = """
        INSERT INTO matches 
        (tournament_name, match_time, home_team, home_logo, away_team, away_logo, 
         home_goals, away_goals, match_status, live_link, slug, iframe_live)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    for _, row in df.iterrows():
        # FRO ("Final result only") მატჩებს არ ვიღებთ
        if re.search(r"\bFRO\b", f"{row['match_time']} {row['match_status']}", re.IGNORECASE):
            continue
        # გოლების ვალიდაცია (შენი PHP-ს is_numeric-ის ანალოგი)
        h_goals = is_valid_score(row['home_goals'])
        a_goals = is_valid_score(row['away_goals'])

        val = (
            str(row['tournament_name']) if pd.notna(row['tournament_name']) else "Unknown",
            clean_match_time(row['match_time']),
            str(row['home_team']),
            str(row['home_logo']),
            str(row['away_team']),
            str(row['away_logo']),
            h_goals, # აქ ჩაჯდება None (NULL), თუ თამაში დაწყებული არაა
            a_goals, # აქ ჩაჯდება None (NULL), თუ თამაში დაწყებული არაა
            clean_match_status(row['match_status']),
            str(row['Live Link']),
            str(row['Slug']),
            "" # iframe_live სვეტი (ცარიელი საწყის ეტაპზე)
        )
        cursor.execute(sql, val)
        
    conn.commit()
    print("🚀 მონაცემები წარმატებით აიტვირთა MySQL-ში!")

except Exception as e:
    print(f"❌ შეცდომა ატვირთვისას: {e}")
finally:
    if 'conn' in locals() and conn.is_connected():
        conn.close()
