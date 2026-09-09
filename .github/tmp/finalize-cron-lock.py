from pathlib import Path

cron = Path('cron/update-prices.php')
text = cron.read_text(encoding='utf-8')
old = "$lockPath=sys_get_temp_dir().'/rgpd-lepotager-update-prices.lock';"
new = "$lockPath=__DIR__.'/private/update-prices.lock';"
if text.count(old) != 1:
    raise SystemExit('Unexpected lock path state')
cron.write_text(text.replace(old, new, 1), encoding='utf-8')

ignore = Path('.gitignore')
content = ignore.read_text(encoding='utf-8')
entry = 'cron/private/update-prices.lock\n'
if entry not in content:
    marker = 'cron/private/observations-private.json\n'
    if content.count(marker) != 1:
        raise SystemExit('Unexpected .gitignore state')
    content = content.replace(marker, marker + entry, 1)
    ignore.write_text(content, encoding='utf-8')
print('lock path finalized')
