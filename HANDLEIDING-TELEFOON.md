# Laptop Monitoring op je telefoon

Deze handleiding bestaat uit twee delen:

- **Deel A: het dashboard bekijken** op je telefoon. Dit werkt op elke telefoon, zowel iPhone als Android.
- **Deel B: je telefoon laten meten**, zodat het dashboard ook gegevens van je telefoon laat zien, zoals batterij, geheugen, opslag en specificaties. Dit werkt alleen op Android.

> **Je online adres:** de app draait op Vercel. Het adres vind je op [vercel.com](https://vercel.com) → project **laptop-monitoring** → *Domains*.
> Jouw adres is `https://laptop-monitoring-rho.vercel.app`. In deze handleiding heet dat adres **`JOUW-ADRES`**.

---

## Deel A: het dashboard op je telefoon bekijken

### 1. Openen en inloggen

1. Open de browser op je telefoon: Safari op een iPhone, Chrome op Android.
2. Ga naar `https://JOUW-ADRES`.
3. Log in met hetzelfde e-mailadres en wachtwoord als op je laptop.

Je telefoon hoeft niet op hetzelfde wifi-netwerk te zitten als je laptop. Het dashboard staat online, dus het werkt ook via 4G of 5G.

### 2. Als app-icoon op je beginscherm zetten

Daarna open je het dashboard met één tik, net als een gewone app.

**iPhone (Safari)**
1. Tik onderaan op de **Deel-knop** (vierkantje met pijl omhoog).
2. Scroll naar beneden en kies **Zet op beginscherm**.
3. Geef het een naam, bijvoorbeeld *Monitoring*, en tik op **Voeg toe**.

**Android (Chrome)**
1. Tik rechtsboven op de **drie puntjes ⋮**.
2. Kies **Toevoegen aan startscherm**. Op sommige telefoons heet dit *App installeren*.
3. Tik op **Toevoegen**.

### 3. Wat zie je waar?

| Onderdeel | Waar | Wat je ziet |
|---|---|---|
| Apparaatknoppen bovenaan | 💻 Laptop / 📱 Telefoon | Groen bolletje: het apparaat heeft net gegevens gestuurd. Grijs: al een tijd niets ontvangen. |
| **Dashboard** | per apparaat | Overzicht: aan sinds, batterij, geheugen, schijf |
| **Browsen** | per apparaat | Bezochte websites, ook incognito als de extensie dat mag zien |
| **Systeem** | per apparaat | Specificaties en open programma's |
| **Inloggen** | per apparaat | Wanneer de laptop is ontgrendeld of aangemeld, en mislukte pogingen |
| **⚙️ Apparaten** | rechtsboven | Apparaten toevoegen of verwijderen, en tokens bekijken |

De pagina's verversen zichzelf, dus je hoeft niet steeds te herladen.

> **Tip:** zie je de menuknoppen niet goed op een klein scherm? Draai je telefoon dan even horizontaal.

---

## Deel B: je Android-telefoon laten meten

Hiermee stuurt je telefoon elke 15 minuten zijn gegevens naar het dashboard. Je doet dit één keer en daarna werkt het vanzelf.

### Stap 1: maak een telefoon-apparaat aan

1. Open het dashboard en ga naar **⚙️ Apparaten**.
2. Maak een apparaat aan met het type **Telefoon**, bijvoorbeeld met de naam *Mijn telefoon*.
3. Je krijgt een **token** te zien, een lange code. Kopieer die en bewaar hem even, want je hebt hem zo nodig.

### Stap 2: installeer Termux en Termux:API

1. Installeer de app **F-Droid** via [f-droid.org](https://f-droid.org). Je telefoon vraagt of je apps van buiten de Play Store wilt toestaan. Zeg ja.
2. Zoek in F-Droid naar **Termux** en installeer het.
3. Zoek in F-Droid naar **Termux:API** en installeer het.

> ⚠️ Installeer **beide** apps via F-Droid en **niet** via de Play Store. De Play Store-versies zijn verouderd, en apps uit twee verschillende bronnen werken niet samen.

### Stap 3: geef toestemmingen

Ga naar **Instellingen → Apps**:

- **Termux:API** → *Machtigingen* → zet **Telefoon** en **Locatie** aan. Die zijn nodig voor provider- en wifi-gegevens.
- **Termux** én **Termux:API** → *Batterij* → kies **Onbeperkt**. Andere namen hiervoor zijn *Niet optimaliseren* of *Geen beperkingen*.

  Sla je dit over, dan stopt Android de metingen na een tijdje.

### Stap 4: zet het script op je telefoon

Het script staat op je laptop in `C:\Laravel\laptop_monitoring\agents\android\phone-agent.sh`.
Zet het in de map **Download** op je telefoon. Dat kan op een van deze manieren:

- **USB-kabel:** sluit de telefoon aan, kies *Bestandsoverdracht*, en kopieer het bestand naar de map `Download`.
- **Google Drive:** upload het bestand op je laptop en download het op je telefoon.
- **E-mail:** mail het bestand naar jezelf en sla de bijlage op je telefoon op.

### Stap 5: instellen in Termux

Open **Termux**. Typ de regels hieronder één voor één, of kopieer en plak ze, en druk na elke regel op Enter.

```bash
pkg update -y
pkg install -y termux-api jq curl
termux-setup-storage
```

Bij `termux-setup-storage` vraagt je telefoon om toegang tot bestanden. Kies **Toestaan**.

Zet daarna het script klaar. Het makkelijkst is het script direct van GitHub te downloaden. Dan kun je stap 4 overslaan:

```bash
curl -fsSL -o ~/phone-agent.sh https://raw.githubusercontent.com/Aart-Verschuure/laptop-monitoring/main/agents/android/phone-agent.sh
chmod +x ~/phone-agent.sh
```

Heb je het bestand via stap 4 in je map Download gezet? Gebruik dan in plaats van de `curl`-regel:

```bash
cp ~/storage/downloads/phone-agent*.sh ~/phone-agent.sh
```

Maak het instellingenbestand aan. **Vervang** `JOUW-ADRES` en `JOUW_TELEFOON_TOKEN` door je eigen adres en token, en plak het hele blok in één keer:

```bash
cat > ~/.laptop-monitoring.conf <<'EOF'
SERVER_URL="https://JOUW-ADRES/api/report"
TOKEN="JOUW_TELEFOON_TOKEN"
EOF
```

### Stap 6: testen

```bash
~/phone-agent.sh
```

- Zie je **`200`**? Dan werkt het. Open het dashboard: het telefoonknopje heeft nu een groen bolletje.
- Zie je **`401`**? Dan klopt het token niet. Controleer het in het instellingenbestand, zie *Problemen oplossen*.
- Zie je een **foutmelding over het adres** of `000`? Dan klopt het adres niet of heb je geen internet. Het adres moet beginnen met `https://` en eindigen op `/api/report`.

De eerste keer kan het een paar seconden duren voordat er iets verschijnt.

### Stap 7: automatisch elke 15 minuten

```bash
termux-job-scheduler --script ~/phone-agent.sh --period-ms 900000 --persisted true
```

Klaar! De telefoon stuurt nu elke 15 minuten zijn gegevens, ook na een herstart. Vaker dan elke 15 minuten staat Android niet toe.

Controleren of de taak actief is:

```bash
termux-job-scheduler --pending
```

Stoppen:

```bash
termux-job-scheduler --cancel-all
```

---

## Extra: ontgrendelingen van je telefoon tellen (optioneel)

Android laat Termux niet zien hoe vaak je je telefoon ontgrendelt. Met de gratis app **MacroDroid** uit de Play Store kan het wel:

1. Maak een nieuwe macro.
2. **Trigger:** *Apparaatgebeurtenissen* → *Scherm ontgrendeld*.
3. **Actie:** *Connectiviteit* → *HTTP-verzoek*.
   - Methode: **POST**
   - URL: `https://JOUW-ADRES/api/report`
   - Header: `Authorization` = `Bearer JOUW_TELEFOON_TOKEN`. Let op de spatie na *Bearer*.
   - Header: `Content-Type` = `application/json`
   - Body:
     ```json
     {"logins":[{"type":"unlock"}]}
     ```
4. Sla de macro op en zet hem aan.

Na een ontgrendeling zie je die in het dashboard onder **Inloggen** bij je telefoon.

## Extra: websites die je op je telefoon bezoekt

Android laat geen browsegeschiedenis uitlezen. Gebruik je **Chrome op je telefoon** met hetzelfde Google-account als op je laptop, dan kan het via synchronisatie:

1. Zet in Chrome op je telefoon *Instellingen → Synchronisatie* aan voor **Geschiedenis** en **Geopende tabbladen**.
2. Open op je laptop de instellingen van de browser-extensie, vink **Tabbladen van mijn andere apparaten** aan en vul je **telefoon-token** in.

Websites die je op je telefoon opent, verschijnen dan bij je telefoon onder **Browsen**.
Incognito-tabbladen op je telefoon worden nooit gesynchroniseerd en blijven dus onzichtbaar.

---

## Wat kan níet op een telefoon

| | Android | iPhone |
|---|---|---|
| Dashboard bekijken | ✅ | ✅ |
| Batterij, geheugen, opslag, specificaties | ✅ via Termux | ❌ iOS staat dit niet toe |
| Ontgrendelingen | ✅ via MacroDroid | ❌ |
| Bezochte websites | ⚠️ alleen via Chrome-synchronisatie | ⚠️ alleen via Chrome-synchronisatie |
| Welke apps open zijn | ❌ | ❌ |
| CPU-gebruik | ❌ sinds Android 8 | ❌ |

---

## Problemen oplossen

| Probleem | Oplossing |
|---|---|
| `pkg: inaccessible or not found` | Termux is bij de eerste start niet goed geïnstalleerd, en je typt in de gewone Android-shell. Ga naar **Instellingen → Apps → Termux → Opslag** en kies **Gegevens wissen**. Open Termux daarna opnieuw met wifi aan, en wacht tot *Welcome to Termux* verschijnt. Helpt dat niet, verwijder Termux dan en installeer het opnieuw via F-Droid. |
| Telefoon blijft **grijs** in het dashboard | Typ `~/phone-agent.sh` in Termux en kijk welke code je krijgt (zie stap 6). |
| `cannot link executable "curl"` | De pakketten in Termux zijn maar half bijgewerkt. Voer `pkg upgrade -y` uit. |
| Eerst werkte het, nu niet meer | Meestal heeft Android de batterijbesparing weer aangezet. Controleer stap 3 opnieuw. |
| `405` of `419` bij het testen | `SERVER_URL` wijst naar een verkeerde pagina. Hij moet precies `https://laptop-monitoring-rho.vercel.app/api/report` zijn, dus niet `/login` of `/apparaten`, en niet het adres van een andere site. |
| `401` bij het testen | Het token klopt niet. Op **⚙️ Apparaten** kun je een nieuw token maken. Zet dat daarna met `nano ~/.laptop-monitoring.conf` in het instellingenbestand. Opslaan doe je met *Ctrl+O* en *Enter*, afsluiten met *Ctrl+X*. |
| `cp: cannot stat …` / *No such file or directory* | Gebruik de `curl`-regel uit stap 5. Die haalt het script rechtstreeks van GitHub. Of kijk met `ls ~/storage/downloads` hoe het bestand op je telefoon heet, want browsers maken er soms `phone-agent.sh.txt` of `phone-agent(1).sh` van. |
| Geen provider- of wifi-gegevens | Geef Termux:API toestemming voor *Telefoon* en *Locatie* (stap 3). |
| Inloggen op het dashboard lukt niet op je telefoon | Gebruik `https://` en niet `http://`. Controleer ook of cookies aanstaan in je browser. |
