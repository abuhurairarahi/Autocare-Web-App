import os
import glob
import re

api_dir = r"c:\xampp\htdocs\AutoCare\public_html\api"
files = glob.glob(os.path.join(api_dir, "**/*.php"), recursive=True)
files.extend(glob.glob(os.path.join(api_dir, "*.php")))

pattern = re.compile(r"function databaseConnection\(\)\s*:\s*PDO\s*\{.*?\n\}", re.MULTILINE | re.DOTALL)

for file in files:
    if "db.php" in file:
        continue
        
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    if "function databaseConnection" in content:
        # Determine relative path from this file to db.php
        dir_path = os.path.dirname(file)
        # depth logic: if in api/ then it's __DIR__ . '/db.php'
        # if in api/admin-api/ it's __DIR__ . '/../db.php'
        if os.path.basename(dir_path) == "api":
            require_str = "require_once __DIR__ . '/db.php';"
        else:
            require_str = "require_once __DIR__ . '/../db.php';"
            
        new_content = pattern.sub(require_str, content)
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated {os.path.basename(file)}")

print("Done")
