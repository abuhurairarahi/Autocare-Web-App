import os
import glob
import subprocess

api_dir = r"c:\xampp\htdocs\AutoCare\public_html\api"
files = glob.glob(os.path.join(api_dir, "**/*.php"), recursive=True)

errors = []
for file in files:
    result = subprocess.run([r"c:\xampp\php\php.exe", "-l", file], capture_output=True, text=True)
    if result.returncode != 0:
        errors.append(f"{file}:\n{result.stdout}\n{result.stderr}")

if errors:
    print("Syntax errors found:")
    for err in errors:
        print(err)
else:
    print("All PHP files passed syntax check.")
