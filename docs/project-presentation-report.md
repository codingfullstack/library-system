# Laravel bibliotekų valdymo sistemos ir Android aplikacijos analizė

Parengta profesionaliai produkto, techninei ir prezentacinei medžiagai.

## 1. Projekto apžvalga

Sistema yra moderni bibliotekų valdymo platforma, sudaryta iš Laravel web sistemos, REST API ir Android aplikacijos. Ji skirta bibliotekoms, kurios nori centralizuotai valdyti knygų katalogą, filialus, fizinius knygų egzempliorius, skolinimus, grąžinimus, rezervacijas, skaitytojus, darbuotojus ir pranešimus.

### Sprendžiama problema

Tipinėse bibliotekose dažnai naudojami atskiri, fragmentuoti procesai: knygų sąrašai vienoje vietoje, skaitytojai kitoje, išdavimai registruojami rankiniu būdu, o filialų duomenys nėra vieningai matomi. Tai sukuria klaidas, dubliavimą, neaiškią atsakomybę ir lėtą aptarnavimą.

Ši sistema problemą sprendžia kaip vientisa platforma:

- vienas katalogas knygoms ir egzemplioriams;
- aiškus filialų ir lokacijų modelis;
- kiekvienas egzempliorius turi inventorinį kodą, QR kodą, būseną ir istoriją;
- nariai gali rezervuoti knygas ir matyti savo paskyrą;
- darbuotojai gali greitai aptarnauti skaitytojus;
- vadovai gali matyti statistiką ir audito informaciją;
- Android aplikacija leidžia dalį darbo atlikti mobiliame įrenginyje.

### Tiksliniai klientai

- viešosios bibliotekos;
- mokyklų ir universitetų bibliotekos;
- savivaldybių bibliotekų tinklai;
- organizacijų, archyvų ar įstaigų vidinės bibliotekos;
- bibliotekų filialų tinklai, kuriems reikalinga kelių bibliotekų arba kelių padalinių izoliacija.

### Nauda

Sistema suteikia ne tik knygų registrą, bet ir pilną operacinį bibliotekos valdymą. Ji mažina rankinio darbo kiekį, leidžia greičiau aptarnauti skaitytojus, suteikia realaus laiko informaciją apie egzempliorių būsenas, padeda valdyti rezervacijų eiles ir suteikia saugų vaidmenimis paremtą administravimą.

### Kuo sistema skiriasi nuo paprastų bibliotekų sistemų

Paprasta bibliotekų sistema dažnai apsiriboja knygų katalogu ir skolinimų žurnalu. Šis projektas turi platesnę architektūrą:

- multi-tenant modelis: skirtingos bibliotekos izoliuotos viena nuo kitos;
- atskirti loginiai knygos įrašai ir fiziniai egzemplioriai;
- QR kodų nuskaitymas ir mobili darbo eiga;
- REST API Android aplikacijai;
- Sanctum token autentifikacija;
- Fortify web autentifikacija ir 2FA;
- audito žurnalas;
- pranešimai web ir mobiliems įrenginiams;
- SEO viešiems puslapiams;
- importai, eksportai ir vadovų ataskaitos.

### Nauda darbuotojams

- Greitesnis knygų išdavimas ir grąžinimas.
- Mažiau rankinio tikrinimo, nes egzempliorių būsenos valdomos automatiškai.
- QR skeneris leidžia rasti egzempliorių be rankinio paieškos darbo.
- Rezervacijų eilė parodo, kam pirmiausia turi būti išduota knyga.
- Narių paieška leidžia greitai identifikuoti skaitytoją.

### Nauda vadovams

- Matoma bibliotekos veiklos apžvalga ir statistika.
- Galima valdyti darbuotojus, bibliotekas, filialus ir lokacijas.
- Audito žurnalas parodo, kas ir kada atliko svarbius veiksmus.
- Duomenys paruošti eksportui ir ataskaitoms.
- Multi-tenant architektūra leidžia centralizuotai aptarnauti kelias bibliotekas.

### Nauda skaitytojams

- Skaitytojai gali prisijungti, matyti savo paskolintas knygas ir rezervacijas.
- Gali jungtis prie viešų bibliotekų.
- Gali gauti pranešimus apie rezervacijos paruošimą, grąžinimą ar kitus įvykius.
- Android aplikacija suteikia patogų mobilų prieinamumą.

## 2. Funkcionalumų analizė

### 1. Viešas portalas

Paskirtis: pristatyti sistemą, bibliotekų sąrašą, pagalbą, kontaktus ir pagrindinę informaciją.

Nauda: klientai ir skaitytojai turi aiškų viešą įėjimą į sistemą.

Sprendžiama problema: sistema nėra vien uždara administracinė platforma; ji turi viešą, SEO paruoštą sluoksnį.

### 2. Bibliotekų valdymas

Paskirtis: kurti, redaguoti ir administruoti bibliotekas.

Nauda: galima valdyti kelias bibliotekas vienoje sistemoje.

Sprendžiama problema: atskirų bibliotekų duomenys nebesimaišo ir gali būti administruojami centralizuotai.

### 3. Bibliotekų viešumas

Paskirtis: bibliotekos turi `is_active` ir `is_public` būsenas.

Nauda: galima atskirti veikiančias ir viešai rodomas bibliotekas nuo vidinių ar išjungtų.

Sprendžiama problema: ne visos bibliotekos turi būti matomos viešame sąraše ar prieinamos narių prisijungimui.

### 4. Filialų valdymas

Paskirtis: valdyti bibliotekos filialus su pavadinimu, kodu, adresu ir miestu.

Nauda: tinka realioms bibliotekų organizacijoms, kurios turi kelis padalinius.

Sprendžiama problema: vien katalogo nepakanka, kai knygos yra skirtinguose filialuose.

### 5. Lokacijų valdymas

Paskirtis: valdyti lentynas, kambarius, sales ar kitas vietas filialuose.

Nauda: darbuotojas žino, kur fiziškai yra egzempliorius.

Sprendžiama problema: mažėja paieškos laikas ir painiava fonde.

### 6. Knygų katalogas

Paskirtis: saugoti knygos pavadinimą, subtitrą, ISBN, aprašymą, leidėją, kategorijas, autorius, metus, kalbą, puslapių skaičių ir viršelį.

Nauda: skaitytojai ir darbuotojai turi centralizuotą paieškos vietą.

Sprendžiama problema: knygos informacija nebedubliuojama kiekvienam fiziniam egzemplioriui.

### 7. Autorių valdymas

Paskirtis: kurti autorius, biografijas, susieti autorius su knygomis.

Nauda: viena knyga gali turėti kelis autorius, vienas autorius gali turėti daug knygų.

Sprendžiama problema: išvengiama netikslių ar dubliuotų autorių įrašų.

### 8. Kategorijų valdymas

Paskirtis: klasifikuoti knygas pagal kategorijas.

Nauda: geresnė paieška, filtravimas ir katalogo struktūra.

Sprendžiama problema: didelis fondas tampa lengviau naršomas.

### 9. Leidėjų valdymas

Paskirtis: saugoti leidyklas ir jų šalį.

Nauda: papildomas bibliografinis tikslumas.

Sprendžiama problema: leidėjo informacija nėra rašoma laisvu tekstu kiekvienoje knygoje.

### 10. Knygų egzemplioriai

Paskirtis: valdyti fizines knygų kopijas su biblioteka, filialu, lokacija, inventoriniu kodu, QR kodu, brūkšniniu kodu, būsena ir pastabomis.

Nauda: viena knyga gali turėti daug egzempliorių skirtingose bibliotekose ir filialuose.

Sprendžiama problema: katalogas nebesupainioja knygos aprašo su realia fizine kopija.

### 11. Egzemplioriaus būsenų gyvavimo ciklas

Paskirtis: keisti egzemplioriaus būsenas: laisva, išduota, prarasta, sugadinta, tvarkoma, nurašyta.

Nauda: fondas matomas realiu laiku.

Sprendžiama problema: darbuotojai žino, ar knyga išduodama, remontuojama, prarasta ar nurašyta.

### 12. Egzemplioriaus būsenų istorija

Paskirtis: saugoti, kada ir kas pakeitė egzemplioriaus būseną.

Nauda: atsiranda atsekamumas.

Sprendžiama problema: galima paaiškinti, kodėl kopija tapo neaktyvi, sugadinta ar nurašyta.

### 13. Knygos išdavimas

Paskirtis: darbuotojas ar administratorius išduoda laisvą egzempliorių nariui.

Nauda: sukuriamas paskolos įrašas, kopija pažymima kaip išduota, registruojamas auditas.

Sprendžiama problema: išdavimai nebevyksta rankiniu žurnalu ir nebelieka neaiškumo, kas turi knygą.

### 14. Knygos grąžinimas

Paskirtis: užbaigti aktyvią paskolą ir grąžinti egzempliorių į laisvą fondą.

Nauda: sistema automatiškai atnaujina kopijos būseną ir praneša vartotojui.

Sprendžiama problema: mažėja klaidų registruojant grąžinimus.

### 15. Vėlavimų skaičiavimas

Paskirtis: paskola turi terminą `due_at`, o modelis apskaičiuoja `is_overdue` ir `overdue_days`.

Nauda: galima greitai matyti vėluojančias knygas.

Sprendžiama problema: darbuotojams nereikia rankiniu būdu tikrinti terminų.

### 16. Rezervacijos

Paskirtis: narys arba darbuotojas gali rezervuoti knygą, kai nėra laisvo egzemplioriaus.

Nauda: skaitytojas patenka į eilę ir gauna pranešimą, kai knyga paruošta.

Sprendžiama problema: išvengiama neformalių rezervacijų ir neaiškios eilės.

### 17. Rezervacijų eilės sinchronizavimas

Paskirtis: sistema automatiškai nustato pirmą eilėje esantį narį ir suteikia atsiėmimo laiką, kai atsiranda laisva kopija.

Nauda: rezervacijų eilė veikia sąžiningai ir automatiškai.

Sprendžiama problema: darbuotojui nereikia rankiniu būdu perskirstyti rezervacijų.

### 18. Rezervacijos atšaukimas

Paskirtis: narys gali atšaukti savo rezervaciją, darbuotojas gali atšaukti su priežastimi.

Nauda: eilė atsinaujina, o narys informuojamas.

Sprendžiama problema: rezervacijos nelieka „pakibusios“.

### 19. Vartotojų valdymas

Paskirtis: administruoti vartotojus, jų aktyvumą ir narystes.

Nauda: darbuotojai ir nariai valdomi vienoje vietoje.

Sprendžiama problema: sumažėja rankinis paskyrų administravimas.

### 20. Bibliotekos narystės

Paskirtis: vartotojas gali turėti aktyvias narystes bibliotekose.

Nauda: vienas skaitytojas gali jungtis prie daugiau nei vienos bibliotekos.

Sprendžiama problema: multi-tenant sistemoje narys nėra pririštas tik prie vienos bibliotekos.

### 21. Nario QR kodas

Paskirtis: narys turi QR identifikatorių, o darbuotojas gali jį nuskaityti.

Nauda: greitesnis skaitytojo identifikavimas.

Sprendžiama problema: nereikia rankiniu būdu ieškoti nario pagal vardą ar el. paštą.

### 22. Knygos kopijos QR kodas

Paskirtis: kiekvienas egzempliorius turi QR kodą.

Nauda: darbuotojas telefonu ar web gali greitai atidaryti kopijos informaciją.

Sprendžiama problema: sumažėja klaidų renkant inventorinį kodą.

### 23. QR nuskaitymo žurnalas

Paskirtis: registruoti QR nuskaitymus, rezultatą ir įrenginį.

Nauda: matomas naudojimo ir klaidų pėdsakas.

Sprendžiama problema: galima diagnozuoti nerastus, blokuotus ar klaidingus nuskaitymus.

### 24. Web pranešimai

Paskirtis: saugoti vartotojų pranešimus sistemoje.

Nauda: narys mato jam svarbius įvykius.

Sprendžiama problema: komunikacija neapsiriboja išoriniais kanalais.

### 25. Push pranešimai Android aplikacijai

Paskirtis: naudoti Firebase Cloud Messaging ir įrenginio tokenus.

Nauda: vartotojas gali būti informuojamas telefone.

Sprendžiama problema: svarbi informacija pasiekia vartotoją ne tik prisijungus prie web.

### 26. Audito žurnalas

Paskirtis: saugoti svarbius administravimo, knygų, egzempliorių, paskolų, rezervacijų ir vartotojų veiksmus.

Nauda: vadovas gali matyti atsakomybę ir pokyčių istoriją.

Sprendžiama problema: sistemoje nelieka „nežinomų“ pakeitimų.

### 27. Dashboard ir ataskaitos

Paskirtis: pateikti bibliotekos veiklos apžvalgą ir eksportuoti ataskaitas.

Nauda: vadovai gauna veiklos rodiklius.

Sprendžiama problema: duomenys nereikalauja rankinio skaičiavimo iš lentelių.

### 28. CSV importas

Paskirtis: importuoti knygas, filialus ir lokacijas iš CSV.

Nauda: greitesnis sistemos užpildymas pradiniais duomenimis.

Sprendžiama problema: nereikia kiekvieno įrašo suvesti rankiniu būdu.

### 29. CSV eksportas

Paskirtis: eksportuoti sąrašus per `exports/{resource}.csv`.

Nauda: duomenis galima naudoti ataskaitoms ar kitoms sistemoms.

Sprendžiama problema: uždarų duomenų problema.

### 30. REST API

Paskirtis: suteikti Android aplikacijai ir galimoms integracijoms struktūruotą prieigą prie duomenų.

Nauda: web sistema tampa platforma, ne tik svetaine.

Sprendžiama problema: mobilioji aplikacija nenaudoja web puslapių „apėjimų“, o dirba per aiškius API endpointus.

### 31. Android aplikacija

Paskirtis: darbuotojams ir nariams suteikti mobilią prieigą.

Nauda: darbuotojas gali skenuoti QR kodą, išduoti ir grąžinti knygą; narys gali matyti paskolas ir rezervacijas.

Sprendžiama problema: bibliotekos darbas nebėra pririštas tik prie stacionaraus kompiuterio.

### 32. Autentifikacija

Paskirtis: web autentifikacija per Laravel Fortify, API autentifikacija per Sanctum tokenus.

Nauda: atskiri kanalai turi jiems tinkamus saugumo mechanizmus.

Sprendžiama problema: web ir mobilus prisijungimas valdomi saugiai ir standartizuotai.

### 33. 2FA

Paskirtis: papildoma web paskyrų apsauga.

Nauda: administratorių paskyros tampa saugesnės.

Sprendžiama problema: vien slaptažodžio nepakanka jautriai bibliotekos valdymo sistemai.

### 34. Rolėmis pagrįsta prieiga

Paskirtis: riboti veiksmus pagal Super Admin, Admin, Darbuotojo ir Nario roles.

Nauda: kiekvienas vartotojas mato ir atlieka tik jam skirtus veiksmus.

Sprendžiama problema: sumažėja netyčinių ar neteisėtų pakeitimų rizika.

### 35. Multi-tenant bibliotekų izoliacija

Paskirtis: per `LibraryContext` ir `BelongsToLibrary` automatiškai riboti duomenis pagal aktyvią biblioteką.

Nauda: skirtingų bibliotekų duomenys izoliuojami.

Sprendžiama problema: viena biblioteka negali matyti ar keisti kitos bibliotekos duomenų, išskyrus super administravimo lygį.

## 3. Vartotojų rolės ir autorizacija

### Rolių paskirtis

- Super Administratorius: visos platformos valdytojas. Gali valdyti bibliotekas, globalius katalogo klasifikatorius, audito žurnalą ir priskirti darbuotojus.
- Administratorius: konkrečios bibliotekos valdytojas. Gali administruoti savo bibliotekos knygas, kopijas, filialus, lokacijas, vartotojus ir operacijas.
- Darbuotojas: operacinis bibliotekos darbuotojas. Gali tvarkyti katalogą, kopijas, išdavimus, grąžinimus, rezervacijas ir narių aptarnavimą savo bibliotekoje.
- Narys: skaitytojas. Gali matyti katalogą, savo paskolas, rezervacijas, pranešimus, jungtis prie viešų bibliotekų ir rezervuoti knygas.

### Funkcijų ir rolių lentelė

| Funkcija | Super Admin | Admin | Darbuotojas | Narys |
|---|---:|---:|---:|---:|
| Viešas portalas | Taip | Taip | Taip | Taip |
| Prisijungimas web | Taip | Taip | Taip | Taip |
| Prisijungimas Android | Taip | Taip | Taip | Taip |
| 2FA nustatymai web | Taip | Taip | Taip | Taip |
| Dashboard | Taip | Taip | Taip | Ne |
| Nario paskyros apžvalga | Ne pagal rolę | Ne pagal rolę | Ne pagal rolę | Taip |
| Viešų bibliotekų sąrašas | Taip | Taip | Taip | Taip |
| Prisijungimas prie viešos bibliotekos | Ne | Ne | Ne | Taip |
| Knygų katalogo peržiūra | Taip | Taip | Taip | Taip |
| Knygos detalės | Taip | Taip | Taip | Taip |
| Knygų kūrimas/redagavimas | Taip | Taip | Taip | Ne |
| Knygų trynimas | Taip | Taip | Taip | Ne |
| Autorių valdymas | Taip | Taip | Taip | Ne |
| Filialų valdymas | Taip | Taip | Taip | Ne |
| Lokacijų valdymas | Taip | Taip | Taip | Ne |
| Egzempliorių valdymas | Taip | Taip | Taip | Ne |
| Egzemplioriaus būsenos keitimas | Taip | Taip | Taip | Ne |
| Knygos išdavimas | Taip | Taip | Taip | Ne |
| Knygos grąžinimas | Taip | Taip | Taip | Ne |
| Aktyvių paskolų sąrašas bibliotekoje | Taip | Taip | Taip | Ne |
| Savo paskolų sąrašas | Ribotai pagal poreikį | Ribotai pagal poreikį | Ribotai pagal poreikį | Taip |
| Rezervacijų sąrašas bibliotekoje | Taip | Taip | Taip | Ne |
| Savo rezervacijų sąrašas | Ne | Ne | Ne | Taip |
| Rezervacijos sukūrimas nariui | Taip | Taip | Taip | Taip, sau |
| Rezervacijos atšaukimas | Taip | Taip | Taip | Taip, savo |
| QR knygos nuskaitymas | Taip | Taip | Taip | Ribotai pagal API teises |
| Nario QR nuskaitymas | Taip | Taip | Taip | Ne |
| Pranešimų peržiūra | Taip | Taip | Taip | Taip |
| Pranešimų žymėjimas skaitytais | Taip | Taip | Taip | Taip |
| Vartotojų valdymas | Taip | Taip | Taip | Ne |
| Narystės valdymas | Taip | Taip | Taip | Ne |
| Bibliotekų valdymas | Taip | Ne | Ne | Ne |
| Darbuotojų priskyrimas bibliotekai | Taip | Ne | Ne | Ne |
| Kategorijų valdymas | Taip | Ne | Ne | Ne |
| Leidėjų valdymas | Taip | Ne | Ne | Ne |
| Audito žurnalas | Taip | Ne | Ne | Ne |
| CSV importas | Taip | Taip | Taip | Ne |
| CSV eksportas | Taip | Taip | Taip | Ribotai pagal prieinamą sąrašą |
| Dashboard eksportas | Taip | Taip | Taip | Ne |

### Policy ir autorizacijos mechanizmai

Autorizacija įgyvendinta keliais sluoksniais:

1. Maršrutų middleware:
   - `auth`;
   - `verified`;
   - `library.context`;
   - `role:superadministratorius,administratorius,darbuotojas`;
   - `role:narys`;
   - `throttle:api-login`, `throttle:api-read`, `throttle:api-sensitive`.

2. `EnsureUserHasRole`:
   - tikrina prisijungusį vartotoją;
   - leidžia pasiekti maršrutą tik turint vieną iš nurodytų efektyvių rolių.

3. `LibraryContext`:
   - nustato aktyvios bibliotekos ID iš užklausos, `X-Library-Id` antraštės, sesijos arba vartotojo numatytos narystės;
   - super administratoriui leidžia veikti plačiau;
   - kitiems vartotojams leidžia tik jiems priklausančią biblioteką.

4. `BelongsToLibrary`:
   - automatiškai prideda Eloquent globalų scope pagal `library_id`;
   - kuriant įrašus gali automatiškai priskirti aktyvią biblioteką;
   - turi metodus `forLibrary` ir `withoutLibraryScope`, kai reikia sąmoningai valdyti scope.

5. `BookCopyPolicy`:
   - `viewAny`: Super Admin, Admin, Darbuotojas;
   - `view`: Super Admin viską, kiti tik savo bibliotekos kopijas;
   - `create`: Super Admin, Admin, Darbuotojas;
   - `update/delete`: Super Admin arba Admin/Darbuotojas savo bibliotekoje.

6. `LoanPolicy`:
   - `viewAny`: visos rolės;
   - `view`: narys mato tik savo paskolą savo bibliotekoje, darbuotojai mato savo bibliotekos paskolas, Super Admin mato viską;
   - `create`: Super Admin, Admin, Darbuotojas;
   - `update`: naudojama knygos kopijos atnaujinimo logikai;
   - `delete`: Super Admin arba Admin savo bibliotekoje.

7. `ReservationPolicy`:
   - `viewAny`: visos rolės;
   - `view`: narys tik savo rezervaciją, darbuotojai savo bibliotekos rezervacijas, Super Admin viską;
   - `create`: visos rolės;
   - `update/delete`: narys tik savo rezervaciją, Admin/Darbuotojas savo bibliotekos rezervacijas, Super Admin viską.

## 4. Sistemos architektūra

### Laravel dalis

Laravel aplikacija atsakinga už:

- web maršrutus ir Blade/Livewire puslapius;
- Fortify autentifikaciją, registraciją, slaptažodžių atkūrimą ir 2FA;
- bibliotekų, katalogo, vartotojų, paskolų ir rezervacijų valdymą;
- duomenų validaciją per Form Request klases;
- dalykinę logiką per Actions;
- duomenų skaitymą per Query klases;
- audito žurnalą;
- SEO viešiems puslapiams;
- pranešimus ir FCM integraciją;
- CSV importus/eksportus.

### API dalis

REST API yra po `/api/auth/*`. Pagrindinės API grupės:

- `POST /api/auth/login`;
- `POST /api/auth/logout`;
- `GET /api/auth/me`;
- knygos ir knygų detalės;
- knygų kopijos ir QR paieška;
- paskolos;
- rezervacijos;
- narių paieška;
- nario dashboard;
- viešos bibliotekos ir prisijungimas prie bibliotekos;
- pranešimai;
- įrenginio tokenai;
- QR nuskaitymas.

Autentifikacija API lygyje vyksta per Laravel Sanctum personal access tokenus. Android aplikacija tokeną siunčia `Authorization: Bearer ...` antrašte.

### Android dalis

Android aplikacija sukurta Kotlin ir Jetpack Compose. Ji naudoja:

- Retrofit API integracijoms;
- OkHttp interceptorių `Authorization` antraštei;
- EncryptedSharedPreferences tokenų saugojimui;
- CameraX ir ML Kit QR nuskaitymui;
- Firebase Cloud Messaging push pranešimams;
- Navigation Compose ekranų navigacijai;
- Material 3 UI komponentus.

### Architektūros schema

```text
Vartotojas
  |
  +-- Web naršyklė
  |     |
  |     v
  |   Laravel web routes
  |     |
  |     v
  |   Controllers / Livewire
  |     |
  |     v
  |   Requests -> Policies -> Actions -> Queries
  |     |
  |     v
  |   Eloquent Models + LibraryContext
  |     |
  |     v
  |   Database
  |
  +-- Android App
        |
        v
      Retrofit API client
        |
        v
      Laravel REST API
        |
        v
      Sanctum auth + middleware + policies
        |
        v
      Actions / Queries / Resources
        |
        v
      Database

Papildomi kanalai:

Laravel Notifications / FCM Service
  -> Firebase Cloud Messaging
  -> Android system notifications

Laravel SEO service
  -> vieši puslapiai
  -> robots.txt
  -> sitemap.xml
  -> OpenGraph / Twitter Cards
```

### Loginė sluoksnių schema

```text
Presentation Layer
  Blade, Livewire, Jetpack Compose

Application Layer
  Controllers, API Controllers, ViewModels

Domain Workflow Layer
  Actions: BorrowBookCopyAction, ReturnBookCopyAction,
  CreateReservationAction, CancelReservationAction,
  SyncReservationQueueAction

Read Model Layer
  Queries: GetLibraryBooksQuery, GetActiveLibraryLoansQuery,
  GetMemberReservationsQuery, SearchLibraryMembersQuery

Authorization Layer
  Middleware, Policies, LibraryContext, BelongsToLibrary

Persistence Layer
  Eloquent Models, Migrations, Database indexes

Integration Layer
  Sanctum, Fortify, FCM, Laravel Echo/Reverb, SEO packages
```

## 5. Duomenų bazės analizė

### `users`

Paskirtis: saugo sistemos vartotojus.

Svarbiausi laukai: `id`, `name`, `email`, `password`, `role`, `phone`, `membership_number`, `is_active`, `email_verified_at`, 2FA laukai.

Ryšiai: turi narystes `library_memberships`, paskolas `loans`, rezervacijas `reservations`, išduotas/gautas paskolas, nuskaitymų žurnalus, įrenginio tokenus.

Naudojimo scenarijai: prisijungimas, rolių kontrolė, nario identifikacija, darbuotojų priskyrimas, paskolų ir rezervacijų savininkas.

### `libraries`

Paskirtis: saugo bibliotekas kaip tenantus.

Svarbiausi laukai: `id`, `name`, `slug`, `code`, `email`, `phone`, `address`, `city`, `is_active`, `is_public`.

Ryšiai: turi narius, filialus, lokacijas, knygų kopijas, paskolas, rezervacijas, scan logs.

Naudojimo scenarijai: bibliotekos administravimas, viešas bibliotekų sąrašas, multi-tenant izoliacija.

### `library_memberships`

Paskirtis: jungia vartotojus su bibliotekomis.

Svarbiausi laukai: `library_id`, `user_id`, `membership_number`, `is_active`, `joined_at`.

Ryšiai: priklauso `libraries` ir `users`.

Naudojimo scenarijai: nario priklausymas bibliotekai, darbuotojo prieiga prie bibliotekos, aktyvios bibliotekos pasirinkimas.

### `authors`

Paskirtis: autorių registras.

Svarbiausi laukai: `name`, `slug`, `bio`.

Ryšiai: daug-su-daug su `books` per `book_author`.

Naudojimo scenarijai: knygų filtravimas, bibliografinis aprašas.

### `categories`

Paskirtis: knygų kategorijos.

Svarbiausi laukai: `name`, `slug`, `description`.

Ryšiai: daug-su-daug su `books` per `book_category`, taip pat senesnis pirminis ryšys per `books.category_id`.

Naudojimo scenarijai: katalogo filtravimas, teminis grupavimas.

### `publishers`

Paskirtis: leidyklų registras.

Svarbiausi laukai: `name`, `country`.

Ryšiai: turi daug `books`.

Naudojimo scenarijai: leidėjo filtrai ir knygos metaduomenys.

### `books`

Paskirtis: loginis knygos įrašas.

Svarbiausi laukai: `title`, `slug`, `subtitle`, `isbn`, `description`, `publisher_id`, `category_id`, `publication_year`, `language`, `page_count`, `edition`, `cover_image`.

Ryšiai: priklauso leidėjui ir kategorijai, turi daug autorių, daug kategorijų, daug egzempliorių, rezervacijų ir paskolų per egzempliorius.

Naudojimo scenarijai: katalogas, paieška, knygos detalė, rezervacijos.

### `book_author`

Paskirtis: jungiamoji knygų ir autorių lentelė.

Svarbiausi laukai: `book_id`, `author_id`.

Ryšiai: `books` ir `authors`.

Naudojimo scenarijai: knygos su keliais autoriais.

### `book_category`

Paskirtis: jungiamoji knygų ir kategorijų lentelė.

Svarbiausi laukai: `book_id`, `category_id`.

Ryšiai: `books` ir `categories`.

Naudojimo scenarijai: viena knyga gali priklausyti kelioms kategorijoms.

### `branches`

Paskirtis: bibliotekų filialai.

Svarbiausi laukai: `library_id`, `name`, `code`, `address`, `city`.

Ryšiai: priklauso bibliotekai, turi lokacijas ir knygų kopijas.

Naudojimo scenarijai: filialų struktūra, egzempliorių vieta.

### `locations`

Paskirtis: fizinės vietos filialuose.

Svarbiausi laukai: `library_id`, `branch_id`, `name`, `code`, `room`, `shelf`, `description`.

Ryšiai: priklauso bibliotekai ir filialui, turi knygų kopijas.

Naudojimo scenarijai: lentynos, kambariai, fondų vietos.

### `book_copies`

Paskirtis: fiziniai knygų egzemplioriai.

Svarbiausi laukai: `library_id`, `book_id`, `branch_id`, `location_id`, `inventory_code`, `qr_code`, `barcode`, `status`, `condition_status`, `acquired_at`, `notes`.

Ryšiai: priklauso bibliotekai, knygai, filialui, lokacijai; turi paskolas, nuskaitymus ir būsenų istoriją.

Naudojimo scenarijai: išdavimas, grąžinimas, QR nuskaitymas, inventorizacija, fondų valdymas.

### `book_copy_status_histories`

Paskirtis: egzemplioriaus būsenų keitimo istorija.

Svarbiausi laukai: `book_copy_id`, `changed_by`, `from_status`, `to_status`, `reason_code`, `reason_notes`, `changed_at`.

Ryšiai: priklauso knygos kopijai ir vartotojui.

Naudojimo scenarijai: atsekamumas, nurašymai, remontas, grąžinimas į fondą.

### `loans`

Paskirtis: knygų skolinimai.

Svarbiausi laukai: `library_id`, `book_copy_id`, `user_id`, `issued_by`, `received_by`, `borrowed_at`, `due_at`, `returned_at`, `status`, `renewal_count`, `notes`.

Ryšiai: priklauso bibliotekai, kopijai, nariui, išdavusiam ir priėmusiam darbuotojui.

Naudojimo scenarijai: aktyvios paskolos, vėlavimai, grąžinimai, nario istorija.

### `reservations`

Paskirtis: knygų rezervacijos.

Svarbiausi laukai: `library_id`, `book_id`, `user_id`, `status`, `reserved_at`, `expires_at`, `fulfilled_at`, `cancelled_at`, `notes`.

Ryšiai: priklauso bibliotekai, knygai ir vartotojui.

Naudojimo scenarijai: laukimo eilė, rezervacijos paruošimas, atšaukimas, įvykdymas.

### `scan_logs`

Paskirtis: QR nuskaitymų istorija.

Svarbiausi laukai: `library_id`, `book_copy_id`, `user_id`, `scan_value`, `scan_type`, `result`, `device_info`.

Ryšiai: priklauso bibliotekai, gali priklausyti kopijai ir vartotojui.

Naudojimo scenarijai: QR veikimo analizė, klaidų diagnostika, inventorizacijos pėdsakas.

### `audit_logs`

Paskirtis: administracinių ir operacinių veiksmų auditas.

Svarbiausi laukai: `user_id`, `library_id`, `action`, `auditable_type`, `auditable_id`, `description`, `metadata`.

Ryšiai: priklauso aktoriui, bibliotekai ir polimorfiniam audituojamam objektui.

Naudojimo scenarijai: atsakomybės atsekimas, incidentų analizė, vadovų kontrolė.

### `user_notifications`

Paskirtis: vidiniai vartotojų pranešimai.

Svarbiausi laukai: `user_id`, `sent_by`, `type`, `title`, `message`, `related_type`, `related_id`, `metadata`, `read_at`.

Ryšiai: priklauso gavėjui, gali turėti siuntėją ir susietą objektą.

Naudojimo scenarijai: rezervacija paruošta, rezervacija atšaukta, knyga grąžinta, sisteminiai pranešimai.

### `notifications`

Paskirtis: Laravel standartinių notifikacijų saugojimas.

Svarbiausi laukai: `id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`.

Ryšiai: polimorfinis `notifiable`.

Naudojimo scenarijai: Laravel notification mechanizmo palaikymas.

### `device_tokens`

Paskirtis: Android įrenginių FCM tokenai.

Svarbiausi laukai: `user_id`, `token`, `token_hash`, `device_name`.

Ryšiai: priklauso vartotojui.

Naudojimo scenarijai: push pranešimai į Android įrenginius, tokenų registracija ir pašalinimas.

### `personal_access_tokens`

Paskirtis: Laravel Sanctum API tokenai.

Svarbiausi laukai: `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`.

Ryšiai: polimorfinis vartotojo tokenas.

Naudojimo scenarijai: Android aplikacijos autentifikacija.

### `seo`

Paskirtis: SEO duomenų saugojimas modeliams.

Svarbiausi laukai: `model_type`, `model_id`, `description`, `title`, `image`, `author`, `robots`, `canonical_url`.

Ryšiai: polimorfinis ryšys su modeliais.

Naudojimo scenarijai: viešų puslapių SEO metaduomenys.

### Sisteminės lentelės

- `sessions`: web sesijos.
- `password_reset_tokens`: slaptažodžio atkūrimas.
- `cache`, `cache_locks`: Laravel cache.
- `jobs`, `job_batches`, `failed_jobs`: eilės ir foniniai darbai.

### ERD tekstinis aprašymas

```text
Library 1 -- N Branch
Library 1 -- N Location
Library 1 -- N BookCopy
Library 1 -- N Loan
Library 1 -- N Reservation
Library 1 -- N ScanLog
Library 1 -- N LibraryMembership

User 1 -- N LibraryMembership
User 1 -- N Loan
User 1 -- N Reservation
User 1 -- N DeviceToken
User 1 -- N UserNotification
User 1 -- N ScanLog

LibraryMembership N -- 1 Library
LibraryMembership N -- 1 User

Book N -- 1 Publisher
Book N -- 1 Category
Book N -- N Author through book_author
Book N -- N Category through book_category
Book 1 -- N BookCopy
Book 1 -- N Reservation

Branch N -- 1 Library
Branch 1 -- N Location
Branch 1 -- N BookCopy

Location N -- 1 Library
Location N -- 1 Branch
Location 1 -- N BookCopy

BookCopy N -- 1 Library
BookCopy N -- 1 Book
BookCopy N -- 1 Branch
BookCopy N -- 0..1 Location
BookCopy 1 -- N Loan
BookCopy 1 -- N ScanLog
BookCopy 1 -- N BookCopyStatusHistory

Loan N -- 1 Library
Loan N -- 1 BookCopy
Loan N -- 1 User as member
Loan N -- 0..1 User as issuer
Loan N -- 0..1 User as receiver

Reservation N -- 1 Library
Reservation N -- 1 Book
Reservation N -- 1 User

AuditLog N -- 0..1 User
AuditLog N -- 0..1 Library
AuditLog N -- 1 auditable polymorphic model
```

## 6. Pagrindiniai procesai

### Knygos išdavimas

Kas vyksta:

1. Darbuotojas pasirenka arba nuskenuoja knygos egzempliorių.
2. Sistema patikrina, ar vartotojas turi teisę atnaujinti kopiją per `BookCopyPolicy`.
3. `BorrowBookCopyAction` pradeda duomenų bazės transakciją.
4. Kopija užrakinama `lockForUpdate`, kad tuo pačiu metu nebūtų išduota du kartus.
5. Tikrinama, ar kopijos būsena yra `laisva`.
6. Ieškomas aktyvus narys toje pačioje bibliotekoje.
7. Tikrinamos aktyvios rezervacijos tai knygai.
8. Jei pirmumo teisę turi kitas narys, sistema reikalauja rezervacijos apėjimo patvirtinimo ir priežasties.
9. Nustatomas grąžinimo terminas, pagal nutylėjimą 14 dienų, jeigu nenurodyta kitaip.
10. Sukuriamas `loans` įrašas.
11. Jei narys turėjo rezervaciją, ji pažymima kaip `įvykdyta`.
12. Sukuriamas pranešimas nariui.
13. Kopijos būsena keičiama į `išduota`.
14. Įrašoma būsenos istorija.
15. Sinchronizuojama rezervacijų eilė.
16. Įrašomas audito įrašas `loan_issued`.

### Knygos grąžinimas

Kas vyksta:

1. Darbuotojas atidaro kopiją arba nuskenuoja QR kodą.
2. Sistema patikrina teisę atnaujinti kopiją.
3. `ReturnBookCopyAction` pradeda transakciją.
4. Kopija užrakinama `lockForUpdate`.
5. Randama aktyvi paskola.
6. Paskola pažymima `grąžinta`, užpildomas `returned_at` ir `received_by`.
7. Nariui sukuriamas pranešimas apie grąžinimą.
8. Kopijos būsena keičiama į `laisva`.
9. Sinchronizuojama rezervacijų eilė.
10. Jei yra laukianti rezervacija, pirmajam eilėje gali būti nustatomas atsiėmimo terminas.
11. Įrašomas audito įrašas `loan_returned`.

### Rezervacija

Kas vyksta:

1. Narys arba darbuotojas pasirenka knygą.
2. Sistema nustato narį: jei veikia narys, rezervacija kuriama jam pačiam; jei darbuotojas, galima nurodyti narį.
3. Nustatoma aktyvi biblioteka.
4. Tikrinama, ar knyga turi kopijų toje bibliotekoje.
5. Tikrinama, ar narys jau neturi aktyviai išduotos tos pačios knygos.
6. Tikrinama, ar narys neturi laukiančios tos pačios knygos rezervacijos.
7. Jei yra laisva kopija, rezervacija neleidžiama, nes knygą galima pasiimti.
8. Sukuriamas `reservations` įrašas.
9. Sinchronizuojama rezervacijų eilė.
10. Įrašomas audito įrašas `reservation_created`.

### Rezervacijos eilė

Kas vyksta:

1. Sistema ieško pasibaigusių rezervacijų ir pažymi jas `pasibaigusi`.
2. Randa pirmą laukiančią rezervaciją pagal `reserved_at`.
3. Jei nėra laisvų kopijų, visiems laukiantiems panaikinamas aktyvus atsiėmimo terminas.
4. Jei atsiranda laisva kopija, pirma rezervacija gauna `expires_at` terminą.
5. Nariui siunčiamas pranešimas `reservation_ready`.
6. Kitų rezervacijų terminai paliekami tušti, kol ateis jų eilė.

### QR nuskaitymas

Kas vyksta:

1. Android aplikacija atidaro `QrScannerScreen`.
2. Prašomas kameros leidimas.
3. CameraX perduoda vaizdą ML Kit barcode scanneriui.
4. Jei QR prasideda `MEM:`, aplikacija naviguoja į nario QR ekraną.
5. Kitu atveju QR laikomas knygos kopijos kodu.
6. Aplikacija kviečia API paiešką pagal QR.
7. Laravel tikrina QR formatą ir ieško kopijos vartotojo bibliotekos kontekste.
8. Jei kopija randama, grąžinama detalė ir teisė `can_manage`.
9. Jei kopija nerandama, grąžinamas klaidos atsakymas.
10. Nuskaitymas registruojamas `scan_logs`.

### Prisijungimas

Web:

1. Vartotojas įveda el. paštą ir slaptažodį.
2. Laravel Fortify patikrina duomenis.
3. Jei įjungta 2FA, vartotojas turi patvirtinti antrą faktorių.
4. Po prisijungimo `LoginResponse` nukreipia pagal rolę.
5. `library.context` nustato aktyvią biblioteką.

Android:

1. Vartotojas įveda el. paštą ir slaptažodį.
2. Aplikacija kviečia `POST /api/auth/login`.
3. Laravel patikrina slaptažodį ir aktyvumą.
4. Sukuriamas Sanctum tokenas `android-app`.
5. Tokenas išsaugomas `EncryptedSharedPreferences`.
6. FCM tokenas sinchronizuojamas su serveriu.
7. Pagal rolę rodoma nario arba darbuotojo pradžia.

## 7. Android aplikacijos analizė

### Technologijos

- Kotlin.
- Jetpack Compose.
- Navigation Compose.
- Retrofit ir Gson.
- OkHttp.
- EncryptedSharedPreferences.
- CameraX.
- ML Kit Barcode Scanning.
- Firebase Messaging.
- Material 3.

### Ekranų sąrašas

| Ekranas | Maršrutas | Paskirtis |
|---|---|---|
| LoginScreen | `login` | Prisijungimas prie sistemos |
| HomeScreen | `home` darbuotojui | Darbuotojo/admin pradinis ekranas |
| MemberHomeScreen | `home` nariui | Nario pradinis ekranas ir apžvalga |
| BooksScreen | `books` | Knygų katalogo peržiūra |
| BookDetailsScreen | `book_details/{bookId}` | Knygos detalės ir kopijos |
| QrScannerScreen | `qr_scanner` | QR kodų nuskaitymas |
| BookByQrScreen | `book_by_qr/{qrCode}` | Knygos kopijos detalė pagal QR |
| MemberByQrScreen | `member_by_qr/{membershipNumber}` | Nario identifikacija pagal QR |
| ActiveLoansScreen | `active_loans` | Aktyvūs bibliotekos išdavimai darbuotojams |
| MemberLoansScreen | `my_loans` | Nario paskolos |
| MemberReservationsScreen | `my_reservations` | Nario rezervacijos |
| MemberReservationsScreen administraciniu režimu | `reservations` | Bibliotekos rezervacijos darbuotojams |
| PublicLibrariesScreen | `public_libraries` | Viešų bibliotekų sąrašas ir prisijungimas |
| NotificationsScreen | `notifications` | Pranešimų sąrašas |
| SettingsScreen | `settings` | Aplikacijos ir paskyros nustatymai |

### Navigacijos schema

```text
Start
  |
  +-- jei nėra tokeno -> login
  |
  +-- jei tokenas yra -> home
        |
        +-- role = narys -> MemberHomeScreen
        |       |
        |       +-- books
        |       +-- book_details/{bookId}
        |       +-- my_loans
        |       +-- my_reservations
        |       +-- public_libraries
        |       +-- notifications
        |       +-- settings
        |
        +-- role = superadministratorius / administratorius / darbuotojas -> HomeScreen
                |
                +-- books
                +-- book_details/{bookId}
                +-- active_loans
                +-- reservations
                +-- qr_scanner
                +-- book_by_qr/{qrCode}
                +-- member_by_qr/{membershipNumber}
                +-- notifications
                +-- settings
```

### API integracijos

Android `ApiService` integruoja:

- login/logout/me;
- įrenginio tokeno registraciją ir šalinimą;
- knygų sąrašą ir detales;
- narių paiešką;
- knygos kopijos išdavimą;
- grąžinimą;
- kopijos būsenos keitimą;
- rezervacijos kūrimą ir sąrašą;
- aktyvias paskolas;
- nario dashboard;
- viešų bibliotekų sąrašą ir prisijungimą;
- pranešimus ir neskaitytų skaičių;
- kopijos paiešką pagal QR;
- nario paiešką pagal narystės numerį;
- narystės pridėjimą pagal nuskaitytą QR.

### Pagrindiniai naudotojo scenarijai

Darbuotojas:

1. Prisijungia.
2. Atidaro QR skenerį.
3. Nuskenuoja knygos egzempliorių.
4. Mato kopijos būseną, vietą, aktyvią paskolą ir galimus veiksmus.
5. Išduoda knygą nariui arba priima grąžinimą.
6. Gali pakeisti kopijos būseną į sugadinta, tvarkoma, prarasta ar nurašyta.

Narys:

1. Prisijungia.
2. Mato savo dashboard.
3. Naršo knygų katalogą.
4. Mato savo paskolas ir rezervacijas.
5. Gali jungtis prie viešų bibliotekų.
6. Gauna pranešimus apie rezervacijas ir kitus įvykius.

## 8. Saugumo analizė

### Autentifikacija

Web naudoja Laravel Fortify. Tai suteikia prisijungimą, registraciją, slaptažodžio atkūrimą, el. pašto patvirtinimą, slaptažodžio patvirtinimą jautriems veiksmams ir 2FA.

API naudoja Sanctum personal access tokenus. Android aplikacija gauna tokeną per `POST /api/auth/login`, saugo jį šifruotose nuostatose ir siunčia `Authorization: Bearer` antraštę.

### Autorizacija

Autorizacija yra kelių sluoksnių:

- middleware pagal roles;
- Policy klasės;
- Form Request `authorize`;
- `Gate::authorize`;
- bibliotekos kontekstas;
- globalūs Eloquent scope pagal `library_id`.

### Sanctum

Sanctum naudojamas Android API tokenams. Stiprybė: tokenai gali būti panaikinami logout metu, API neturi naudoti web sesijos, o mobilioji aplikacija gali saugiai autentifikuotis.

### 2FA

2FA laukai įtraukti į `users` lentelę, o Fortify maršrutai aktyvūs. Tai svarbi apsauga administratorių ir darbuotojų paskyroms.

### CSRF

Web formai naudojamas Laravel CSRF mechanizmas. Viešų puslapių head turi `csrf-token`. API mobiliai aplikacijai naudoja tokeninę autentifikaciją.

### SQL Injection apsauga

Sistema naudoja Eloquent, Query Builder, validaciją ir parametrizuotas užklausas. Tai reikšmingai mažina SQL injection riziką.

### XSS apsauga

Blade pagal nutylėjimą escapina kintamuosius. SEO ir aprašymo tekstai ribojami ir stripinami per `SeoService::description`. Reikia atsargiai vertinti vietas, kur naudojamas `{!! !!}`, bet SEO išvedimas ateina iš specializuoto paketo.

### Duomenų izoliacija tarp bibliotekų

Stipriausia multi-tenant saugumo dalis:

- aktyvi biblioteka nustatoma per `LibraryContext`;
- modeliai su `BelongsToLibrary` automatiškai filtruojami pagal `library_id`;
- Policy klasės tikrina `belongsToLibrary`;
- darbuotojų ir administratorių veiksmai ribojami jų bibliotekai;
- Super Admin yra aiškiai atskiras globalus vaidmuo.

### Stipriosios vietos

- Fortify web autentifikacija.
- Sanctum API tokenai.
- 2FA palaikymas.
- Šifruotas tokenų saugojimas Android aplikacijoje.
- Role middleware.
- Policy klasės jautriems veiksmams.
- Transakcijos ir `lockForUpdate` išdavimui/grąžinimui.
- Audit log svarbiems veiksmams.
- Rate limiting API login, read ir sensitive endpointams.
- QR nuskaitymų registravimas.
- FCM tokenų hash saugojimas.
- `robots.txt` neleidžia indeksuoti vidinių puslapių.

### Galimi patobulinimai

- Android `usesCleartextTraffic="true"` gamyboje pakeisti į `false`, o debug režime leisti tik lokalią aplinką.
- API login galėtų tikrinti 2FA arba turėti atskirą mobilią 2FA eigą, jei reikalaujama aukštesnio saugumo.
- Sanctum tokenams galima taikyti galiojimo laiką ir rotaciją.
- Įdiegti detalesnį abilities modelį tokenams, pavyzdžiui `mobile:read`, `mobile:staff-actions`.
- Sustiprinti CSP antraštes viešiems ir vidiniams puslapiams.
- Įtraukti centralizuotą saugumo antraščių middleware: HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy.
- Įdiegti detalesnį auditą prisijungimams, nesėkmingiems prisijungimams ir 2FA įvykiams.
- Peržiūrėti tekstų koduotės problemą kai kuriuose failuose, nes matomi sugadinti lietuviški simboliai.
- Įtraukti automatinius saugumo testus dėl cross-tenant prieigos.
- Android release `API_BASE_URL` dabar pagal nutylėjimą yra `https://api.example.com/api/`; gamyboje reikėtų užtikrinti realų URL.

## 9. SEO analizė

### Kas jau įgyvendinta

- Vieši puslapiai: `/`, `/bibliotekos`, `/apie`, `/kontaktai`, `/pagalba`.
- Lietuviški URL keliai.
- `robots.txt` su leidimu viešai svetainei ir draudimu vidiniams puslapiams.
- `sitemap.xml` su pagrindiniais viešais puslapiais.
- Canonical tagai per SEO paketą.
- OpenGraph tagai.
- Twitter Card tagai.
- `lt_LT` locale.
- Viešų puslapių unikalūs title ir description.
- Vidiniai katalogo, paskyros, paskolų, rezervacijų ir valdymo puslapiai žymimi `noindex,nofollow`.
- Ne production aplinkoje robots default priverstinai `noindex,nofollow`.
- Bibliotekos modelis turi dinaminį SEO ir gali `noindex` neaktyvias ar neviešas bibliotekas.
- Knygos modelis turi SEO duomenis, bet katalogo puslapiai autentifikuoti ir žymimi `noindex`.

### Vertinimas

SEO strategija šiam projektui logiška: indeksuojami tik reprezentaciniai vieši puslapiai, o operacinė bibliotekos informacija saugoma nuo paieškos variklių. Tai tinka sistemai, kurioje katalogas ir paskyros duomenys nėra visiškai vieši.

### Rekomendacijos

- Jei ateityje norima viešo katalogo, sukurti atskirą viešą knygų indeksavimo strategiją.
- Patikslinti `robots.txt` sitemap domeną pagal tikrą produkcijos domeną.
- Viešų bibliotekų individualiems puslapiams apsispręsti: jei jie turi būti SEO matomi, neblokuoti `/bibliotekos/`; jei tik prisijungusiems, dabartinė strategija gera.
- Pridėti `Organization` ir `WebSite` schema.org struktūrą pagrindiniame puslapyje.
- Pridėti `BreadcrumbList` schema viešiems puslapiams.
- Paruošti reprezentacinį OpenGraph paveikslėlį vietoje auth iliustracijos fallback.
- Užtikrinti, kad sitemap generavimas būtų automatizuotas deploy metu.
- Pridėti meta aprašymų valdymą per administracinę sąsają.

## 10. Sistemos privalumai

### Techniniai privalumai

1. Laravel 13 ir PHP 8.3 pagrindas.
2. Aiškus web ir API atskyrimas.
3. Sanctum token autentifikacija Android aplikacijai.
4. Fortify su 2FA web naudotojams.
5. Multi-tenant duomenų izoliacija per `LibraryContext` ir globalius scope.
6. Policy pagrįsta autorizacija.
7. Transakciniai išdavimai ir grąžinimai.
8. QR kodų palaikymas ir scan log.
9. Audito žurnalas.
10. SEO sluoksnis viešiems puslapiams.

### Verslo privalumai

11. Tinka kelioms bibliotekoms ir filialams.
12. Mažina rankinio darbo ir klaidų kiekį.
13. Leidžia centralizuotai valdyti bibliotekų tinklą.
14. Suteikia vadovams veiklos rodiklius ir eksportus.
15. Gali būti pristatoma savivaldybėms kaip skaitmenizavimo platforma.

### Vartotojo privalumai

16. Skaitytojas mato savo paskolas ir rezervacijas.
17. Darbuotojas gali dirbti mobiliu įrenginiu.
18. Rezervacijų eilės aiškios ir sąžiningos.
19. Pranešimai informuoja apie svarbius įvykius.
20. QR kodai pagreitina kasdienį aptarnavimą.

## 11. Konkurenciniai pranašumai

### Palyginimas su tipinėmis bibliotekų sistemomis

| Sritis | Tipinė sistema | Ši sistema |
|---|---|---|
| Bibliotekų skaičius | Dažnai viena biblioteka | Kelios bibliotekos su izoliacija |
| Filialai | Ribotas palaikymas | Filialai ir lokacijos |
| Egzemplioriai | Dažnai supaprastinta | Pilnas fizinės kopijos modelis |
| QR | Nebūtinai yra | Integruotas QR skenavimas |
| Mobilumas | Dažnai nėra | Android aplikacija |
| API | Ribota arba nėra | REST API |
| Saugumas | Paprastos rolės | Fortify, Sanctum, 2FA, Policies |
| Auditas | Ribotas | Veiksmų audito žurnalas |
| Rezervacijos | Pagrindinės | Eilės sinchronizavimas |
| SEO | Nedažnai | Viešų puslapių SEO |

### Kuo esame geresni

- Sistema apima visą bibliotekos operacinį ciklą.
- Galima valdyti kelių bibliotekų tinklą.
- Mobilioji aplikacija skirta realiam darbui, o ne tik informacijos peržiūrai.
- QR kodai leidžia greitai identifikuoti ir knygas, ir narius.
- Audito žurnalas suteikia skaidrumo.
- Duomenų izoliacija leidžia vieną platformą naudoti kelioms organizacijoms.

### Kuo išsiskiriame

- Multi-tenant architektūra.
- Laravel + Android ekosistema.
- Rezervacijų eilės automatizavimas.
- Pranešimai ir FCM integracija.
- SEO paruoštas viešas sluoksnis.
- Aiški dalykinė architektūra: Actions, Queries, Policies, Resources.

### Kuriama vertė

Sistema kuria vertę trimis lygiais:

- operaciniu: greitesnis aptarnavimas;
- vadybiniu: geresnis matomumas ir kontrolė;
- strateginiu: bibliotekos skaitmenizacija ir galimybė plėstis į kelių bibliotekų tinklą.

## 12. Galimos ateities plėtros

### Bibliotekoms

1. RFID integracija.
2. Savitarnos knygų išdavimo terminalas.
3. Inventorizacijos režimas su masiniu QR/RFID skenavimu.
4. Tarpbibliotekinis skolinimas.
5. Knygų perkėlimo tarp filialų procesas.
6. Sugadintų knygų remonto darbų modulis.
7. Knygų įsigijimo ir pirkimų planavimas.
8. Fondo nurašymo komisijos procesas.
9. Periodinių leidinių valdymas.
10. Dokumentų ir archyvinių fondų modulis.

### Skaitytojams

11. Skaitytojo virtuali kortelė Android aplikacijoje.
12. Knygos pratęsimo funkcija.
13. Mėgstamų knygų sąrašas.
14. Skaitymo istorija ir rekomendacijos.
15. Pranešimai apie artėjantį grąžinimo terminą.
16. Knygos įvertinimai ir atsiliepimai.
17. Viešas renginių kalendorius.
18. Skaitytojo mokėjimų ar delspinigių peržiūra.
19. Savitarnos rezervacijos atsiėmimo QR.
20. Knygų rekomendacijos pagal kategorijas.

### Administravimui

21. Detalesnis leidimų modelis per permission lenteles.
22. Organizacijų ir savivaldybių lygmens dashboard.
23. SLA ir aptarnavimo rodikliai.
24. Duomenų kokybės patikros.
25. Importų istorija ir klaidų taisymo vedlys.
26. Automatinės atsarginės kopijos valdymo panelėje.
27. Integracija su nacionaliniais bibliografiniais katalogais.
28. SSO per Microsoft, Google ar savivaldybės tapatybės sistemą.
29. Detali incidentų ir saugumo įvykių analizė.
30. Role/permission administravimo UI.

### Mobiliai aplikacijai

31. Offline režimas inventorizacijai.
32. Push pranešimų nustatymai pagal tipą.
33. QR kortelės generavimas nariui telefone.
34. Darbuotojo greitas režimas išdavimui/grąžinimui.
35. Kameros istorija ir paskutiniai nuskaitymai.
36. Biometrinis prisijungimas.
37. Deep links į konkrečią rezervaciją ar paskolą.
38. Planšetės režimas bibliotekos darbuotojams.
39. Android widget su artėjančiais terminais.
40. Programėlės lokalizacijos į anglų ir kitas kalbas.

### Dirbtiniam intelektui

41. Knygų rekomendacijos pagal skaitymo istoriją.
42. Automatinis knygos kategorijos pasiūlymas pagal aprašymą.
43. Aprašymų generavimas iš ISBN/metaduomenų.
44. Pokalbių asistentas skaitytojams.
45. Darbuotojo asistentas inventorizacijos klaidoms aptikti.
46. Paklausos prognozavimas pagal rezervacijas.
47. Vėlavimų rizikos prognozė.
48. Knygų fondo spragų analizė.
49. Natūralios kalbos paieška kataloge.
50. Automatinės ataskaitų santraukos vadovams.

## 13. SWOT analizė

### Strengths

- Pilnas bibliotekos darbo ciklas vienoje platformoje.
- Multi-tenant architektūra.
- Laravel web sistema ir Android aplikacija.
- QR kodų skenavimas.
- Rezervacijų eilės automatizavimas.
- Pranešimai ir FCM.
- 2FA, Sanctum, Policy mechanizmai.
- Audito žurnalas.
- SEO paruoštas viešas portalas.
- Aiški techninė struktūra su Actions ir Queries.

### Weaknesses

- Kai kuriuose failuose matomi sugadinti lietuviški simboliai, tai gali kenkti vartotojo patirčiai.
- Android release API URL pagal nutylėjimą dar nėra realus projekto domenas.
- Android manifest leidžia cleartext traffic, gamyboje tai reikėtų riboti.
- API mobili 2FA eiga nėra aiškiai išplėtota.
- Globalios rolės reiškia, kad vartotojo rolė nėra skirtinga kiekvienai bibliotekai, nors narystės leidžia kelių bibliotekų priklausymą.
- Dalis funkcijų yra labiau administracinės nei visiškai savitarnos.

### Opportunities

- Savivaldybių bibliotekų tinklų skaitmenizavimas.
- Mokyklų bibliotekų modernizavimas.
- RFID ir savitarnos įrenginių integracija.
- AI rekomendacijos ir katalogo praturtinimas.
- Nacionalinių katalogų integracijos.
- SaaS modelis bibliotekoms.
- Mobilios inventorizacijos modulis.
- Duomenimis grįstos ataskaitos vadovams.

### Threats

- Konkurencija su brandžiomis bibliotekų informacinėmis sistemomis.
- Viešojo sektoriaus pirkimų ciklų ilga trukmė.
- Duomenų migracijos sudėtingumas iš senų sistemų.
- Aukšti saugumo ir privatumo reikalavimai.
- Priklausomybė nuo FCM mobiliems push pranešimams.
- Reikia nuolatinės priežiūros, kad technologijų versijos būtų saugios.

## 14. Investuotojo / kliento prezentacijos medžiaga

### 30 sekundžių pristatymas

Tai moderni bibliotekų valdymo platforma, kuri sujungia web sistemą, REST API ir Android aplikaciją. Ji leidžia valdyti kelias bibliotekas, filialus, knygų katalogą, fizinius egzempliorius, skolinimus, grąžinimus, rezervacijas, vartotojus ir pranešimus. Sistema išsiskiria multi-tenant architektūra, QR kodų nuskaitymu, saugia rolėmis pagrįsta prieiga ir mobiliu darbuotojų bei skaitytojų aptarnavimu.

### 2 minučių pristatymas

Bibliotekos kasdien susiduria su tais pačiais iššūkiais: kur tiksliai yra knyga, ar egzempliorius laisvas, kas jį turi, kas laukia eilėje, ar skaitytojas priklauso šiai bibliotekai, kas atliko pakeitimą. Ši sistema šiuos procesus sujungia į vieną platformą.

Laravel web dalis leidžia administruoti bibliotekas, filialus, lokacijas, katalogą, autorius, kategorijas, leidėjus, vartotojus, egzempliorius ir audito informaciją. REST API suteikia saugią prieigą Android aplikacijai. Android aplikacija leidžia prisijungti, naršyti katalogą, skenuoti QR kodus, išduoti ir grąžinti knygas, matyti rezervacijas, paskolas ir pranešimus.

Svarbiausia, kad sistema yra multi-tenant: kelios bibliotekos gali naudoti tą pačią platformą, bet jų duomenys izoliuojami. Super administratorius mato visą platformą, o administratoriai ir darbuotojai dirba tik savo bibliotekos kontekste. Tai leidžia sistemą diegti ne tik vienoje bibliotekoje, bet ir visame bibliotekų tinkle.

### 5 minučių pristatymas

Ši platforma sprendžia bibliotekų skaitmenizacijos problemą. Daug bibliotekų turi katalogą, bet neturi modernaus, mobilaus, kelioms bibliotekoms pritaikyto operacinio valdymo. Čia kiekviena knyga turi bibliografinį įrašą, o kiekvienas fizinis egzempliorius turi savo inventorinį kodą, QR kodą, filialą, lokaciją, būseną ir istoriją.

Darbuotojo darbas tampa paprastesnis: jis gali nuskenuoti QR kodą, pamatyti kopijos būseną, išduoti knygą nariui, priimti grąžinimą arba pažymėti egzempliorių kaip sugadintą, prarastą ar nurašytą. Sistema transakcijose saugo išdavimus ir grąžinimus, todėl sumažėja klaidų rizika.

Skaitytojui sistema suteikia savitarną: jis gali prisijungti, matyti savo paskolas, rezervacijas, gauti pranešimus ir jungtis prie viešų bibliotekų. Rezervacijų eilė valdoma automatiškai: kai atsiranda laisva kopija, pirmas eilėje esantis skaitytojas gauna atsiėmimo terminą ir pranešimą.

Vadovui sistema suteikia kontrolę: dashboard, eksportai, audito žurnalas, vartotojų ir darbuotojų valdymas, kelių bibliotekų administravimas. Saugumo pusėje naudojamas Fortify, Sanctum, 2FA, Policy mechanizmai ir bibliotekos konteksto izoliacija.

Tai nėra tik katalogas. Tai platforma, kuri gali augti į savivaldybės, mokyklų tinklo ar organizacijos bibliotekų valdymo sprendimą.

### 10 minučių pristatymas

Pradžioje verta parodyti problemą: bibliotekos turi daug fizinių objektų, daug skaitytojų, kelis filialus ir daug kasdienių operacijų. Jei visa tai valdoma rankiniu būdu arba keliose atskirose sistemose, atsiranda klaidos, prarandama kontrolė, sunku greitai aptarnauti skaitytoją.

Šis projektas pateikia vientisą sprendimą. Pagrindas yra Laravel web sistema. Ji valdo viešą portalą, administracinę dalį, bibliotekas, filialus, lokacijas, katalogą, autorius, kategorijas, leidėjus, vartotojus, narystes, paskolas, rezervacijas, pranešimus ir auditą. Duomenų bazėje aiškiai atskiriama knyga ir jos fiziniai egzemplioriai. Tai leidžia realiai valdyti fondą: ta pati knyga gali būti keliose bibliotekose, keliuose filialuose ir skirtingose lentynose.

Antras sluoksnis yra REST API. API leidžia Android aplikacijai dirbti su tais pačiais duomenimis: prisijungti, gauti katalogą, matyti knygos detales, nuskaityti QR, išduoti, grąžinti, kurti rezervacijas, matyti aktyvias paskolas ir pranešimus. API apsaugota Sanctum tokenais ir throttle taisyklėmis.

Trečias sluoksnis yra Android aplikacija. Ji suteikia mobilumą, kuris bibliotekos darbuotojui yra labai praktiškas. Darbuotojas gali su telefonu nueiti prie lentynos, nuskenuoti egzempliorių ir atlikti veiksmą vietoje. Skaitytojas gali matyti savo paskolas, rezervacijas ir gauti push pranešimus.

Svarbi architektūrinė idėja yra multi-tenant modelis. Kiekviena biblioteka turi savo kontekstą. `LibraryContext` nustato aktyvią biblioteką, o `BelongsToLibrary` automatiškai filtruoja duomenis. Policy klasės užtikrina, kad darbuotojas nematytų kitos bibliotekos duomenų. Super administratorius yra išimtinis platformos valdytojas.

Verslo prasme tai reiškia, kad viena platforma gali aptarnauti ne vieną įstaigą, o visą bibliotekų tinklą. Technologiškai tai reiškia, kad sistema paruošta augti: yra indeksai našumui, auditas, pranešimai, API, SEO, importai ir eksportai.

Pabaigoje galima akcentuoti ateities kelią: RFID, savitarna, AI rekomendacijos, tarpbibliotekinis skolinimas, savivaldybės lygmens dashboard ir mobilioji inventorizacija.

## 15. PowerPoint struktūra

| Skaidrė | Tema | Ką rodyti | Ką pasakoti | Ekrano nuotraukos |
|---:|---|---|---|---|
| 1 | Pavadinimas | Produkto pavadinimas, trumpa frazė | Moderni bibliotekų valdymo platforma web ir Android aplinkai | Viešo puslapio hero arba dashboard |
| 2 | Problema | Skaidulė su 4 problemomis | Rankinis darbas, duomenų fragmentacija, filialų painiava, lėtas aptarnavimas | Galima naudoti katalogo ir paskolų kontrastą |
| 3 | Sprendimas | Architektūros mini schema | Viena sistema: web, API, Android | Sistemos schema |
| 4 | Kam skirta | Bibliotekos, savivaldybės, mokyklos, organizacijos | Sistema tinka tiek vienai bibliotekai, tiek tinklui | Viešų bibliotekų sąrašas |
| 5 | Pagrindinės rolės | Rolių lentelė | Super Admin, Admin, Darbuotojas, Narys | Valdymo meniu |
| 6 | Knygų katalogas | Katalogo vaizdas | Knygos aprašas atskirtas nuo kopijų | Knygų sąrašas |
| 7 | Knygos detalė | Knygos puslapis su kopijomis | Matoma prieinamumas, autoriai, kategorijos, kopijos | Knygos detalė |
| 8 | Egzemplioriai | Kopijų lentelė | Kiekviena fizinė kopija turi statusą, filialą, lokaciją, QR | Egzemplioriaus detalė |
| 9 | Išdavimas | Išdavimo forma | Darbuotojas išduoda kopiją nariui, sistema tikrina rezervacijas | Borrow dialog/form |
| 10 | Grąžinimas | Grąžinimo veiksmas | Aktyvi paskola uždaroma, kopija tampa laisva | Return mygtukas |
| 11 | Rezervacijos | Rezervacijų sąrašas | Eilė valdoma automatiškai | Rezervacijų puslapis |
| 12 | QR procesas | QR skenerio schema | Kamera, ML Kit, API, kopijos detalė | Android QR ekranas |
| 13 | Android aplikacija | Ekranų koliažas | Mobilus darbas darbuotojams ir savitarna nariams | Login, Home, QR, Notifications |
| 14 | Pranešimai | Pranešimų sąrašas | Web ir push pranešimai svarbiems įvykiams | Notifications screen |
| 15 | Architektūra | Sluoksnių schema | Laravel, API, Actions, Queries, DB, Android | Tekstinė schema |
| 16 | Duomenų modelis | Supaprastintas ERD | Library, User, Book, BookCopy, Loan, Reservation | ERD diagrama |
| 17 | Saugumas | Saugumo punktai | Fortify, Sanctum, 2FA, Policies, tenant izoliacija | Settings/security |
| 18 | SEO | Viešų puslapių SEO | robots, sitemap, canonical, OG, Twitter Cards | robots/sitemap arba viešas puslapis |
| 19 | Nauda klientui | 3 stulpeliai | Darbuotojams, vadovams, skaitytojams | Dashboard arba katalogas |
| 20 | Konkurencinis pranašumas | Palyginimo lentelė | Ne tik katalogas, o platforma | Lentelė |
| 21 | Ateities planai | Roadmap | RFID, savitarna, AI, tarpbibliotekinis skolinimas | Roadmap grafika |
| 22 | Demonstracijos planas | Demo žingsniai | Ką auditorija pamatys gyvai | Sąrašas |
| 23 | Išvada | Viena stipri žinutė | Sistema paruošta augti su biblioteka | Produkto ekranas |
| 24 | Klausimai | Kontaktai | Pakviesti diskusijai | Neutralus fonas |

## 16. Demonstracijos scenarijus

### 1. Prisijungimas

Ką spausti: atidaryti login puslapį, įvesti darbuotojo prisijungimą, prisijungti.

Ką rodyti: nukreipimą į dashboard.

Ką sakyti: „Pradedame nuo darbuotojo prisijungimo. Sistema naudoja saugią Laravel autentifikaciją, o jautriems naudotojams gali būti įjungtas dviejų faktorių patvirtinimas.“

### 2. Dashboard

Ką spausti: atidaryti `/dashboard`.

Ką rodyti: statistiką, filtrus, eksportą.

Ką sakyti: „Vadovas arba darbuotojas mato operacinę bibliotekos būklę: fondą, paskolas, rezervacijas ir veiklos rodiklius.“

### 3. Knygų katalogas

Ką spausti: meniu pasirinkti knygas.

Ką rodyti: paiešką, filtrus, knygų sąrašą.

Ką sakyti: „Katalogas yra bendra bibliografinė bazė, bet prieinamumas skaičiuojamas pagal fizines kopijas.“

### 4. Knygos detalė

Ką spausti: atidaryti konkrečią knygą.

Ką rodyti: autorius, kategorijas, leidėją, egzempliorius.

Ką sakyti: „Čia matome skirtumą tarp knygos kaip kūrinio ir egzempliorių kaip realių fizinių objektų.“

### 5. Knygos išdavimas

Ką spausti: pasirinkti laisvą egzempliorių, išduoti nariui.

Ką rodyti: nario paiešką, terminą, patvirtinimą.

Ką sakyti: „Išdavimas vyksta transakcijoje. Sistema patikrina, ar kopija laisva, ar narys priklauso bibliotekai, ar nėra rezervacijos kitam skaitytojui.“

### 6. Rezervacija

Ką spausti: sukurti rezervaciją knygai, kuri neturi laisvų kopijų.

Ką rodyti: rezervacijos sukūrimą ir sąrašą.

Ką sakyti: „Rezervacija patenka į eilę. Kai kopija grąžinama, sistema automatiškai paruošia pirmą rezervaciją.“

### 7. Grąžinimas

Ką spausti: grąžinti išduotą egzempliorių.

Ką rodyti: statuso pasikeitimą į laisvą, pranešimą, rezervacijos eilės atsinaujinimą.

Ką sakyti: „Grąžinimas ne tik uždaro paskolą, bet ir gali automatiškai aktyvuoti rezervacijos atsiėmimą.“

### 8. QR kodas web

Ką spausti: atidaryti egzemplioriaus QR puslapį.

Ką rodyti: QR kodą ir kopijos identifikaciją.

Ką sakyti: „Kiekviena fizinė kopija gali būti identifikuojama QR kodu, todėl sumažėja rankinio suvedimo klaidų.“

### 9. Android QR nuskaitymas

Ką spausti: Android aplikacijoje atidaryti QR skenerį.

Ką rodyti: kameros skenerį, po nuskaitymo atsidariusią kopijos informaciją.

Ką sakyti: „Darbuotojas gali atlikti veiksmą vietoje: prie lentynos, prie aptarnavimo stalo ar inventorizacijos metu.“

### 10. Android nario scenarijus

Ką spausti: prisijungti nario paskyra.

Ką rodyti: nario dashboard, paskolas, rezervacijas, viešas bibliotekas.

Ką sakyti: „Skaitytojas mato savo santykį su biblioteka: ką turi paėmęs, ko laukia ir prie kokių bibliotekų yra prisijungęs.“

### 11. Pranešimai

Ką spausti: atidaryti notifications web arba Android.

Ką rodyti: pranešimą apie rezervaciją ar grąžinimą.

Ką sakyti: „Komunikacija integruota į sistemą. Vartotojas informuojamas apie svarbius įvykius.“

### 12. Administravimas

Ką spausti: atidaryti valdymo meniu: bibliotekos, filialai, vartotojai, auditas.

Ką rodyti: vartotojų/narysčių valdymą ir audito įrašus.

Ką sakyti: „Administravimas užbaigia platformos ciklą: valdome ne tik knygas, bet ir organizacinę struktūrą bei atsakomybę.“

## 17. Galimi auditorijos klausimai ir atsakymai

1. Klausimas: Kokią pagrindinę problemą sprendžia sistema?
   Atsakymas: Ji sujungia bibliotekos katalogą, fizinių egzempliorių valdymą, skolinimus, grąžinimus, rezervacijas, vartotojus ir mobilią prieigą į vieną saugią platformą.

2. Klausimas: Ar sistema skirta vienai bibliotekai?
   Atsakymas: Ne tik. Ji turi multi-tenant architektūrą, todėl gali aptarnauti kelias bibliotekas ir jų filialus.

3. Klausimas: Kaip atskiriami skirtingų bibliotekų duomenys?
   Atsakymas: Naudojamas `LibraryContext`, `BelongsToLibrary` globalus scope, Policy klasės ir narystės pagal biblioteką.

4. Klausimas: Kas yra Super Administratorius?
   Atsakymas: Tai platformos valdytojas, galintis administruoti visas bibliotekas, globalius klasifikatorius ir audito informaciją.

5. Klausimas: Ką gali Administratorius?
   Atsakymas: Administratorius valdo savo bibliotekos katalogą, filialus, lokacijas, vartotojus, kopijas, paskolas ir rezervacijas.

6. Klausimas: Kuo Darbuotojas skiriasi nuo Administratoriaus?
   Atsakymas: Darbuotojas orientuotas į kasdienes operacijas: išdavimą, grąžinimą, rezervacijas, kopijų ir katalogo tvarkymą savo bibliotekos ribose.

7. Klausimas: Ką gali Narys?
   Atsakymas: Narys gali matyti katalogą, savo paskolas, rezervacijas, pranešimus, jungtis prie viešų bibliotekų ir kurti savo rezervacijas.

8. Klausimas: Ar knyga ir egzempliorius yra tas pats?
   Atsakymas: Ne. Knyga yra bibliografinis įrašas, o egzempliorius yra fizinė kopija su inventoriniu kodu, QR kodu, filialu, lokacija ir būsena.

9. Klausimas: Kodėl toks atskyrimas svarbus?
   Atsakymas: Nes ta pati knyga gali turėti daug fizinių kopijų skirtinguose filialuose ir kiekviena kopija gali turėti skirtingą būseną.

10. Klausimas: Kaip sistema apsaugo nuo dvigubo išdavimo?
    Atsakymas: Išdavimas vyksta duomenų bazės transakcijoje su `lockForUpdate`, todėl kopija užrakinama išdavimo metu.

11. Klausimas: Kas vyksta, jei kopija jau išduota?
    Atsakymas: Sistema neleidžia jos išduoti dar kartą ir grąžina validacijos klaidą.

12. Klausimas: Kaip veikia rezervacijos?
    Atsakymas: Rezervacija kuriama tik tada, kai nėra laisvos kopijos, narys neturi tos pačios knygos aktyvios paskolos ar laukiančios rezervacijos.

13. Klausimas: Ar rezervacijų eilė automatinė?
    Atsakymas: Taip. `SyncReservationQueueAction` nustato pirmą eilėje esantį narį ir praneša, kai knyga paruošta.

14. Klausimas: Ar darbuotojas gali apeiti rezervaciją?
    Atsakymas: Taip, bet sistema reikalauja patvirtinimo ir priežasties, o veiksmas audituojamas.

15. Klausimas: Ar narys gali atšaukti rezervaciją?
    Atsakymas: Taip, narys gali atšaukti savo laukiančią rezervaciją.

16. Klausimas: Ar darbuotojas gali atšaukti nario rezervaciją?
    Atsakymas: Taip, jei jis turi atitinkamą rolę toje bibliotekoje, o atšaukimo priežastis registruojama.

17. Klausimas: Kaip veikia QR kodai?
    Atsakymas: Knygų kopijos turi QR kodus, o Android aplikacija juos nuskaito per kamerą ir ML Kit. Tada API grąžina kopijos duomenis.

18. Klausimas: Ar QR nuskaitymai registruojami?
    Atsakymas: Taip, `scan_logs` saugo nuskaitymo reikšmę, tipą, rezultatą, vartotoją, kopiją ir įrenginio informaciją.

19. Klausimas: Ar galima skenuoti nario QR?
    Atsakymas: Taip, Android aplikacija atskiria nario QR pagal `MEM:` prefiksą ir atidaro nario paieškos scenarijų.

20. Klausimas: Ar sistema turi mobilią aplikaciją?
    Atsakymas: Taip, Android aplikacija sukurta Kotlin ir Jetpack Compose.

21. Klausimas: Kam skirta Android aplikacija?
    Atsakymas: Darbuotojams ji padeda skenuoti, išduoti, grąžinti ir valdyti kopijas, o nariams leidžia matyti paskolas, rezervacijas ir pranešimus.

22. Klausimas: Kaip Android aplikacija jungiasi prie serverio?
    Atsakymas: Per Retrofit REST API, naudodama Sanctum Bearer tokeną.

23. Klausimas: Kur Android saugo prisijungimo tokeną?
    Atsakymas: `EncryptedSharedPreferences`, naudojant Android Security Crypto.

24. Klausimas: Ar sistema palaiko push pranešimus?
    Atsakymas: Taip, naudojamas Firebase Cloud Messaging ir `device_tokens` lentelė.

25. Klausimas: Kokie pranešimai siunčiami?
    Atsakymas: Pavyzdžiui, rezervacija paruošta, rezervacija įvykdyta, rezervacija atšaukta, knyga grąžinta, vėlavimo ar termino priminimai.

26. Klausimas: Ar yra audito žurnalas?
    Atsakymas: Taip, audituojami katalogo, kopijų, bibliotekų, vartotojų, paskolų ir rezervacijų veiksmai.

27. Klausimas: Kodėl auditas svarbus?
    Atsakymas: Jis leidžia atsakyti, kas, kada ir kodėl atliko pakeitimą.

28. Klausimas: Ar sistema turi 2FA?
    Atsakymas: Taip, Fortify 2FA laukai ir maršrutai yra įtraukti web dalyje.

29. Klausimas: Ar API turi 2FA?
    Atsakymas: Dabartinė API autentifikacija naudoja Sanctum tokenus; mobili 2FA eiga būtų rekomenduojamas ateities patobulinimas jautresnėms aplinkoms.

30. Klausimas: Ar sistema apsaugota nuo SQL injection?
    Atsakymas: Taip, naudojamas Eloquent, Query Builder, validacija ir parametrizuotos užklausos.

31. Klausimas: Ar sistema apsaugota nuo XSS?
    Atsakymas: Blade pagal nutylėjimą escapina duomenis, o SEO tekstai valomi ir ribojami. Reikia atidžiai prižiūrėti vietas, kur naudojamas raw HTML.

32. Klausimas: Ar galima importuoti duomenis?
    Atsakymas: Taip, yra CSV importų mechanizmas knygoms, filialams ir lokacijoms.

33. Klausimas: Ar galima eksportuoti duomenis?
    Atsakymas: Taip, yra sąrašų CSV eksportas ir dashboard ataskaitų eksportas.

34. Klausimas: Ar sistema turi SEO?
    Atsakymas: Taip, vieši puslapiai turi title, description, canonical, OpenGraph, Twitter Card ir sitemap.

35. Klausimas: Ar katalogas indeksuojamas Google?
    Atsakymas: Dabartinėje strategijoje autentifikuoti katalogo puslapiai žymimi `noindex,nofollow`, todėl viešai neindeksuojami.

36. Klausimas: Kodėl katalogas neindeksuojamas?
    Atsakymas: Nes tai operacinė sistemos dalis. Jei klientas norėtų viešo katalogo, galima sukurti atskirą SEO strategiją.

37. Klausimas: Ar sistema gali veikti savivaldybės mastu?
    Atsakymas: Taip, multi-tenant modelis ir kelių bibliotekų valdymas tam tinkamas.

38. Klausimas: Ar vienas narys gali priklausyti kelioms bibliotekoms?
    Atsakymas: Taip, tam naudojama `library_memberships` lentelė.

39. Klausimas: Ar vartotojas gali turėti skirtingas roles skirtingose bibliotekose?
    Atsakymas: Dabartinėje architektūroje rolė yra vartotojo lygio, o narystės apibrėžia bibliotekų priklausymą. Jei reikėtų skirtingų rolių per biblioteką, tai būtų natūrali plėtros kryptis.

40. Klausimas: Kaip valdoma kopijos būsena?
    Atsakymas: Kopija turi statusą ir leidžiamus perėjimus: laisva, išduota, prarasta, sugadinta, tvarkoma, nurašyta.

41. Klausimas: Ar galima keisti išduotos kopijos būseną?
    Atsakymas: Ne, gyvavimo ciklo keitimas blokuojamas, kol kopija turi aktyvią paskolą.

42. Klausimas: Kaip nustatomas vėlavimas?
    Atsakymas: Paskolos modelis lygina `due_at` su dabartiniu laiku ir apskaičiuoja `is_overdue` bei `overdue_days`.

43. Klausimas: Ar sistema turi testų?
    Atsakymas: Taip, yra feature testai autentifikacijai, 2FA, API, pranešimams, rezervacijoms, valdymui, auditui, SEO ir dashboard funkcijoms.

44. Klausimas: Ar sistema palaiko realaus laiko funkcijas?
    Atsakymas: Projekte yra Reverb, Laravel Echo ir pranešimų testai, todėl architektūra paruošta realaus laiko pranešimams.

45. Klausimas: Ką reikėtų pagerinti prieš produkciją?
    Atsakymas: Sutvarkyti koduotės problemas, užtikrinti realius API URL, išjungti cleartext traffic Android release režime, sustiprinti saugumo antraštes ir peržiūrėti tokenų galiojimą.

46. Klausimas: Ar galima integruoti su RFID?
    Atsakymas: Taip, modelis jau turi fizinių kopijų lygį, todėl RFID būtų natūrali QR alternatyva ar papildymas.

47. Klausimas: Ar galima integruoti su kitomis sistemomis?
    Atsakymas: Taip, REST API suteikia pagrindą integracijoms, o ateityje galima pridėti webhookus arba specializuotus importus/eksportus.

48. Klausimas: Ar sistema tinkama mokykloms?
    Atsakymas: Taip, ji tinka mokyklų bibliotekoms, nes palaiko narius, katalogą, išdavimus, rezervacijas ir filialų/lokacijų struktūrą.

49. Klausimas: Kokia yra didžiausia produkto vertė?
    Atsakymas: Tai ne vien katalogas, o pilna bibliotekos operacinė platforma su mobiliu darbu, saugumu, rezervacijų logika ir kelių bibliotekų palaikymu.

50. Klausimas: Kokia būtų tolesnė produkto vystymo kryptis?
    Atsakymas: RFID, savitarnos terminalai, mobilioji inventorizacija, AI rekomendacijos, detalus permission modelis ir savivaldybės lygmens analitika.

## Baigiamoji išvada

Projektas yra stiprus modernios bibliotekų valdymo platformos pagrindas. Jis turi aiškų domeno modelį, web administravimą, REST API, Android aplikaciją, multi-tenant izoliaciją, QR procesus, pranešimus, auditą ir SEO sluoksnį. Verslo požiūriu sistema gali būti pristatoma kaip skaitmenizacijos sprendimas bibliotekoms ir bibliotekų tinklams. Techniniu požiūriu ji turi pakankamai brandžią struktūrą, kad galėtų būti toliau plečiama į savitarną, RFID, AI rekomendacijas ir savivaldybės masto valdymą.
