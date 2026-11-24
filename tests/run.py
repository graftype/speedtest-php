import os, pathlib, subprocess, tempfile, time
root=pathlib.Path(__file__).resolve().parent
with tempfile.TemporaryDirectory() as d:
    port=pathlib.Path(d)/'port'
    server=subprocess.Popen(['python3',str(root/'fixture.py'),str(port)])
    try:
        for _ in range(100):
            if port.exists(): break
            time.sleep(.02)
        subprocess.run(['php',str(root/'transfer.php'),port.read_text()],check=True)
    finally:
        server.terminate()
        server.wait()

for script in ["server-redirect.php", "relay.php"]:
    subprocess.run(["php", str(root/script)], check=True)
