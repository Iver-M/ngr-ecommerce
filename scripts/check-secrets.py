"""Check tracked application files without printing credential values."""
from pathlib import Path
import re
import subprocess
import sys

files = subprocess.check_output(["git", "ls-files", "-z"]).decode().split("\0")
findings = set()
patterns = [
    re.compile(r"SG\.[A-Za-z0-9_-]{15,}\.[A-Za-z0-9_-]{15,}"),
    re.compile(r"(?:ghp_|github_pat_)[A-Za-z0-9_]{20,}"),
]
application_patterns = [
    re.compile(r"\$mail->Password\s*=\s*(['\"])[^'\"]+\1"),
    re.compile(r"define\(\s*['\"]PAYPAL_CLIENT_SECRET['\"]\s*,\s*(['\"])[^'\"]+\1"),
]
for filename in filter(None, files):
    if (filename == "config/local.php" or filename.endswith(".env")
            or filename.startswith("uploads/return_videos/")
            or filename.endswith((".sql", ".sql.gz"))):
        findings.add(filename)
    file = Path(filename)
    if file.suffix.lower() not in {".php", ".js", ".json", ".md", ".yml", ".yaml"}:
        continue
    text = file.read_text(encoding="utf-8", errors="replace")
    if any(pattern.search(text) for pattern in patterns):
        findings.add(filename)
    if (file.suffix == ".php" and filename.startswith(("app/", "config/", "includes/"))
            and any(pattern.search(text) for pattern in application_patterns)):
        findings.add(filename)
    if filename == "config/db.php" and re.search(r"\$pass\s*=\s*(['\"])[^'\"]+\1", text):
        findings.add(filename)
if findings:
    print("Private files or credential patterns detected:")
    print("\n".join(sorted(findings)))
    sys.exit(1)
print("Tracked files passed the credential-pattern check.")
