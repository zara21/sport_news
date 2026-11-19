import os
import sys

import mysql.connector
import pandas as pd

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

db_config = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "auth_system",
}

IMPORT_PATH = os.path.dirname(os.path.abspath(__file__))
LEAGUE_FILES = {
    "premier_league_table.csv": "premier_league",
    "la_liga_table.csv": "la_liga",
    "serie_a_table.csv": "serie_a",
    "ligue_1_table.csv": "ligue_1",
    "bundesliga_table.csv": "bundesliga",
}


def normalize_table(file_name):
    file_path = os.path.join(IMPORT_PATH, file_name)
    df = pd.read_csv(file_path)

    if "league_name" in df.columns:
        return df

    if file_name not in LEAGUE_FILES:
        print(f"⚠️ გამოტოვებულია: {file_name} (league_name ვერ დადგინდა)")
        return pd.DataFrame()

    df = df.rename(columns={
        "Position": "position",
        "Team": "team",
        "P": "played",
        "W": "wins",
        "D": "draws",
        "L": "losses",
        "Score": "score",
        "GoalDiff": "goal_diff",
        "Pts": "points",
    })
    df["league_name"] = LEAGUE_FILES[file_name]
    df["position"] = df["position"].astype(str).str.replace(".", "", regex=False)
    return df


try:
    csv_files = [f for f in os.listdir(IMPORT_PATH) if f.endswith("_table.csv")]
    frames = []

    for file_name in csv_files:
        df = normalize_table(file_name)
        required = {"league_name", "position", "team", "played", "wins", "draws", "losses", "score", "goal_diff", "points"}
        if df.empty or not required.issubset(df.columns):
            print(f"⚠️ გამოტოვებულია: {file_name} (არასრული სვეტები)")
            continue
        frames.append(df[list(required)])

    if not frames:
        raise RuntimeError("ჩასატვირთი standings მონაცემები ვერ მოიძებნა; ცხრილი არ გასუფთავებულა.")

    all_rows = pd.concat(frames, ignore_index=True)

    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()

    print("🧹 league_standings ცხრილის გასუფთავება...")
    cursor.execute("TRUNCATE TABLE league_standings")

    sql = """
        INSERT INTO league_standings
        (league_name, position, team, played, wins, draws, losses, score, goal_diff, points)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    for _, row in all_rows.iterrows():
        cursor.execute(sql, (
            row["league_name"], row["position"], row["team"],
            row["played"], row["wins"], row["draws"],
            row["losses"], row["score"], row["goal_diff"], row["points"],
        ))

    conn.commit()
    print(f"🚀 standings განახლდა: {len(all_rows)} ჩანაწერი.")

except Exception as e:
    print(f"❌ შეცდომა: {e}")
finally:
    if "conn" in locals() and conn.is_connected():
        conn.close()
