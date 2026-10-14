import os
import re

html_dir = r"C:\Users\rahih\Desktop\AutoCare\public_html\pages"

for root, dirs, files in os.walk(html_dir):
    for file in files:
        if file.endswith('.html'):
            file_path = os.path.join(root, file)
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            new_content = re.sub(r'src="../../Scripts/Admin/admin\.js"', 'src="../../assets/js/admin/admin.js"', content)
            
            if new_content != content:
                with open(file_path, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f"Updated {file_path}")
