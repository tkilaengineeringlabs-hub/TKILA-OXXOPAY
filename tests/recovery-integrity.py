from pathlib import Path
import hashlib, zipfile
root = Path(__file__).resolve().parent.parent
baseline = root / 'tests/fixtures/v1.0.15.zip'
assert hashlib.sha256(baseline.read_bytes()).hexdigest() == 'f632a04acaed6d5abba1d7fc907152b570fa24c076ffb5c1cd8d10f15e60b47c', 'Baseline ZIP changed'
with zipfile.ZipFile(baseline) as z:
    # PHP is immutable except the visual template and the exact authorized version header.
    frozen = [n for n in z.namelist() if n.endswith('.php') and n != 'tkila-oxxo-pay/templates/confirmation.php']
    for name in frozen:
        path = root / name.removeprefix('tkila-oxxo-pay/')
        expected = z.read(name)
        if name == 'tkila-oxxo-pay/spin_checkout.php':
            assert expected.count(b'Version: 1.0.15') == 1
            expected = expected.replace(b'Version: 1.0.15', b'Version: 1.0.21', 1)
        assert hashlib.sha256(path.read_bytes()).digest() == hashlib.sha256(expected).digest(), f'Unauthorized PHP difference: {path}'
    for name in ['assets/js/oxxo-confirmation.js', 'spin_wallet_guard.php', 'spin_wallet_split.php']:
        assert (root/name).read_bytes() == z.read('tkila-oxxo-pay/'+name), name
print(f'PASS SHA-256: baseline ZIP and {len(frozen)} PHP files (only exact version header permitted); original JS and wallet preserved')
