import os
import re

html_dir = r"C:\Users\rahih\Desktop\AutoCare\public_html\pages"

def get_relative_path(from_path_abs, to_path_abs):
    # both are like /pages/admin or /pages/vehicleowner/dashboard.html
    from_dir = os.path.dirname(from_path_abs)
    
    # split into components
    from_parts = [p for p in from_dir.split('/') if p]
    to_parts = [p for p in to_path_abs.split('/') if p]
    
    # find common prefix
    i = 0
    while i < len(from_parts) and i < len(to_parts) and from_parts[i] == to_parts[i]:
        i += 1
        
    up_dirs = ['..'] * (len(from_parts) - i)
    down_dirs = to_parts[i:]
    
    rel_path = '/'.join(up_dirs + down_dirs)
    if not rel_path:
        rel_path = os.path.basename(to_path_abs)
    elif not rel_path.startswith('.') and up_dirs == []:
        rel_path = './' + rel_path
        # Actually if they are in same dir, up_dirs is empty, down_dirs has 1 element
        # so rel_path is just the filename. 
        if len(down_dirs) == 1:
            rel_path = down_dirs[0]
            
    return rel_path

def fix_links():
    for root, dirs, files in os.walk(html_dir):
        for file in files:
            if file.endswith('.html'):
                file_path = os.path.join(root, file)
                
                # file's virtual absolute path
                rel_to_public = os.path.relpath(file_path, r"C:\Users\rahih\Desktop\AutoCare\public_html")
                file_virtual_abs = '/' + rel_to_public.replace('\\', '/')
                
                with open(file_path, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                def replacer(match):
                    full_match = match.group(0)
                    href = match.group(1)
                    
                    if href.startswith('/HTML/'):
                        href = '/pages/' + href[6:]
                    
                    if href.startswith('/pages/'):
                        rel = get_relative_path(file_virtual_abs, href)
                        return f'href="{rel}"'
                    
                    return full_match

                # replace hrefs
                new_content = re.sub(r'href=["\'](/pages/[^"\']+|/HTML/[^"\']+)["\']', replacer, content)
                
                # also fix src="/pages/manager/assets/engine.jpg" etc
                def src_replacer(match):
                    full_match = match.group(0)
                    src = match.group(1)
                    if src.startswith('/pages/'):
                        rel = get_relative_path(file_virtual_abs, src)
                        return f'src="{rel}"'
                    return full_match
                    
                new_content = re.sub(r'src=["\'](/pages/[^"\']+)["\']', src_replacer, new_content)

                if new_content != content:
                    with open(file_path, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Updated {file_path}")

fix_links()
