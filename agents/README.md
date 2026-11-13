# Agents installeren

Het dashboard laat alleen zien wat de *agents* opsturen. Er zijn drie agents:

| Agent | Waar | Wat het verstuurt |
|---|---|---|
| `windows/` | laptop | open programma's, CPU, geheugen, schijf, batterij, uptime, specificaties, inlogmomenten |
| `browser-extension/` | Chrome / Edge op de laptop | elke bezochte pagina, ook in incognito |
| `android/` | Android-telefoon (Termux) | alle telefoonspecificaties, geheugen, opslag, batterij, uptime |

Maak eerst in het dashboard op **Apparaten** een apparaat aan: een "Laptop" en een "Telefoon".
Je krijgt dan per apparaat een **token** te zien. De browser-extensie gebruikt hetzelfde token als de laptop.

---

## 1. Laptop (Windows)

Open **PowerShell als administrator** in de map `agents\windows` en voer uit:

```powershell
powershell -ExecutionPolicy Bypass -File .\install.ps1 -ServerUrl http://localhost:8000/api/report -Token JOUW_LAPTOP_TOKEN
```

- De agent draait daarna elke minuut op de achtergrond, als geplande taak `LaptopMonitoringAgent`.
- De bestanden komen in `C:\ProgramData\LaptopMonitoring`. Bij problemen kijk je in `agent.log`.
- Administrator-rechten zijn nodig om inlogmomenten uit het Windows Security-logboek te lezen.
- Verwijderen: `.\install.ps1 -Uninstall`

**Wat telt als "inloggen"?** Aanmelden bij Windows, ontgrendelen (PIN, wachtwoord of Windows Hello) en extern bureaublad.
Mislukte pogingen, zoals een verkeerde PIN, worden apart geteld.

> Tip: met *Snel opstarten* aan zet Windows de laptop bij afsluiten niet echt uit, en loopt de "aan sinds"-teller door.
> Wil je dat de teller bij afsluiten opnieuw begint, zet dan Snel opstarten uit
> (Configuratiescherm → Energiebeheer → "Kies wat de aan/uit-knoppen doen").

## 2. Browser-extensie (ook incognito)

1. Ga in Chrome naar `chrome://extensions` (in Edge: `edge://extensions`).
2. Zet **Ontwikkelaarsmodus** aan.
3. Klik op **Uitgepakte extensie laden** en kies de map `agents/browser-extension`.
4. Vul op de instellingenpagina die opent de server-URL (`http://localhost:8000`) en je **laptop-token** in.
5. **Belangrijk voor incognito:** klik bij de extensie op **Details** en zet **Toestaan in incognito** aan.

Chrome slaat zelf niets op van incognito-tabbladen. De extensie ziet die pagina's alleen als de schakelaar uit stap 5 aanstaat.
Gebruik je meerdere browsers, installeer de extensie dan in elke browser.

## 3. Telefoon (Android)

1. Installeer **Termux** en **Termux:API** via [F-Droid](https://f-droid.org). De Play Store-versies zijn verouderd.
2. Geef Termux:API in de Android-instellingen toestemming voor *Telefoon* en *Locatie*. Die zijn nodig voor provider- en wifi-gegevens.
3. In Termux:

```bash
pkg install termux-api jq curl
termux-setup-storage
cp /sdcard/Download/phone-agent.sh ~/phone-agent.sh   # kopieer het script eerst naar je telefoon
chmod +x ~/phone-agent.sh
cat > ~/.laptop-monitoring.conf <<'EOF'
SERVER_URL="http://IP-VAN-JE-LAPTOP:8000/api/report"
TOKEN="JOUW_TELEFOON_TOKEN"
EOF
~/phone-agent.sh          # test: moet 200 tonen
termux-job-scheduler --script ~/phone-agent.sh --period-ms 900000 --persisted true   # elke 15 min
```

**Let op:** de telefoon moet de laptop kunnen bereiken. Start de server dan met
`php artisan serve --host=0.0.0.0 --port=8000` en gebruik het wifi-IP-adres van je laptop (`ipconfig`).
Windows Firewall vraagt de eerste keer of PHP verkeer mag ontvangen. Kies dan alleen *Privénetwerken*.

### Wat kan níet op Android

Android geeft apps zonder root-rechten geen toegang tot:
- welke andere apps er open zijn;
- de browsegeschiedenis (en incognito wordt nergens opgeslagen);
- het CPU-gebruik (sinds Android 8);
- het aantal keer ontgrendelen.

**Ontgrendelingen toch tellen** kan met een automatiserings-app zoals **MacroDroid** of **Tasker**:

- Trigger: *Scherm ontgrendeld* (MacroDroid) of *Event → Display → Display Unlocked* (Tasker)
- Actie: *HTTP-verzoek* (POST) naar `http://IP-VAN-JE-LAPTOP:8000/api/report`
  - Header `Authorization: Bearer JOUW_TELEFOON_TOKEN`
  - Header `Content-Type: application/json`
  - Body: `{"logins":[{"type":"unlock"}]}`. Het tijdstip van ontvangst wordt dan als ontgrendeltijd gebruikt.

### iPhone

iOS laat helemaal geen achtergrondscripts toe die specificaties uitlezen. Dit deel werkt daarom alleen op Android.
