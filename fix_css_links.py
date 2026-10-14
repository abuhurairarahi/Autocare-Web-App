import os
import re

html_dir = r"C:\Users\rahih\Desktop\AutoCare\public_html\pages"

def fix_css_paths():
    for root, dirs, files in os.walk(html_dir):
        for file in files:
            if file.endswith('.html'):
                file_path = os.path.join(root, file)
                
                rel_to_public_html = os.path.relpath(root, r"C:\Users\rahih\Desktop\AutoCare\public_html")
                
                depth = len(rel_to_public_html.split(os.sep))
                prefix = "../" * depth + "assets/css/"
                
                with open(file_path, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                def replacer(match):
                    full_match = match.group(0)
                    href_val = match.group(1)
                    
                    if 'Styles/' in href_val:
                        parts = href_val.split('Styles/')
                        after_styles = parts[1] 
                        
                        sub_parts = after_styles.split('/')
                        if len(sub_parts) > 1:
                            sub_parts[0] = sub_parts[0].lower()
                        after_styles = '/'.join(sub_parts)
                        
                        return f'href="{prefix}{after_styles}"'
                    return full_match

                new_content = re.sub(r'href=["\']([^"\']*Styles/[^"\']+)["\']', replacer, content)
                
                if new_content != content:
                    with open(file_path, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Updated {file_path}")

fix_css_paths()
