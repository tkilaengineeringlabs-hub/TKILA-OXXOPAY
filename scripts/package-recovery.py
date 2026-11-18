#!/usr/bin/env python3
"""Gate release on tests; preserve frozen payment PHP and validate the exact authorized version header."""
import hashlib, os, pathlib, subprocess, zipfile
root = pathlib.Path(__file__).resolve().parent.parent
os.chdir(root)
subprocess.run(['python3','tests/recovery-integrity.py'],check=True)
for test in ['environment','confirmation-render','amounts','checkout-guard','split-wallet','split-integration','webhook-ownership']:
    subprocess.run(['php',f'tests/{test}.php'],check=True)
subprocess.run(['node','--check','assets/js/oxxo-ticket.js'],check=True)
subprocess.run(['node','tests/png-download.cjs'],check=True)
for file in ['spin_block_gateway.php','spin_plugin.php','spin_checkout.php','templates/confirmation.php']:
    subprocess.run(['php','-l',file],check=True)
version = '1.0.19'
allowed = {'VERSION','templates/confirmation.php','assets/css/oxxo-confirmation.css','assets/js/oxxo-ticket.js'}
with zipfile.ZipFile('tests/fixtures/v1.0.15.zip') as baseline:
    files = {n: baseline.read(n) for n in baseline.namelist() if not n.endswith('/')}
for name in list(files):
    relative = name.removeprefix('tkila-oxxo-pay/')
    if relative == 'VERSION': continue
    content = pathlib.Path(relative).read_bytes()
    if content != files[name]:
        if relative == 'spin_checkout.php':
            assert files[name].count(b'Version: 1.0.15') == 1
            assert content == files[name].replace(b'Version: 1.0.15', b'Version: 1.0.19', 1), 'Unauthorized checkout logic change'
        else:
            assert relative in allowed, f'Unauthorized package change: {relative}'
        files[name] = content
files['tkila-oxxo-pay/VERSION'] = (version+'\n').encode()
files['tkila-oxxo-pay/assets/js/oxxo-ticket.js'] = pathlib.Path('assets/js/oxxo-ticket.js').read_bytes()
out = pathlib.Path(f'tkila-oxxo-pay-v{version}-recovery.zip')
temp = out.with_suffix('.zip.tmp')
try:
    with zipfile.ZipFile(temp,'w',zipfile.ZIP_DEFLATED,9) as package:
        for name, content in sorted(files.items()): package.writestr(name,content)
    with zipfile.ZipFile(temp) as package, zipfile.ZipFile('tests/fixtures/v1.0.15.zip') as baseline:
        assert package.testzip() is None
        assert set(package.namelist()) == set(files)
        for name in baseline.namelist():
            if name.endswith('/'):continue
            if name == 'tkila-oxxo-pay/spin_checkout.php':
                assert package.read(name) == baseline.read(name).replace(b'Version: 1.0.15', b'Version: 1.0.19', 1)
            elif name.removeprefix('tkila-oxxo-pay/') not in allowed:
                assert package.read(name) == baseline.read(name), name
        assert package.read('tkila-oxxo-pay/VERSION').decode().strip() == version
        assert b'Version: 1.0.19' in package.read('tkila-oxxo-pay/spin_checkout.php')
    pathlib.Path('VERSION').write_text(version+'\n')
    temp.replace(out)
except BaseException:
    temp.unlink(missing_ok=True)
    raise
print('PASS package round-trip, exact baseline inventory + independent PNG script')
print(out, hashlib.sha256(out.read_bytes()).hexdigest())
