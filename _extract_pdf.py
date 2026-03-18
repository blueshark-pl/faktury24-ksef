import PyPDF2
import sys

reader = PyPDF2.PdfReader(r'G:\2025\partnersc\faktury24\webroot\broszura-informacyjna-dotyczaca-struktury-logicznej-fa-3.pdf')
print(f'Total pages: {len(reader.pages)}')

with open(r'G:\2025\partnersc\faktury24\_broszura_text.txt', 'w', encoding='utf-8') as f:
    for i, page in enumerate(reader.pages):
        text = page.extract_text()
        f.write(f'\n=== PAGE {i+1} ===\n')
        f.write(text if text else '(no text)')
        f.write('\n')

print('Done - saved to _broszura_text.txt')
