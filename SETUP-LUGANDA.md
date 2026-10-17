# Luganda learning app: setup

## 1. Install packages
```bash
npm install          # adds Alpine.js
```
(No new Composer packages are needed.)

## 2. Create the PostgreSQL database
```bash
sudo -u postgres psql -c "CREATE DATABASE luganda;"
# optional: a dedicated user
sudo -u postgres psql -c "CREATE USER luganda WITH PASSWORD 'choose-a-password';"
sudo -u postgres psql -c "ALTER DATABASE luganda OWNER TO luganda;"
```
Then put the matching values in `.env`:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=luganda
DB_USERNAME=luganda
DB_PASSWORD=choose-a-password
```
PHP needs the `pdo_pgsql` extension: `sudo apt install php8.3-pgsql` (match your PHP version).

## 3. Build the database and starter content
```bash
php artisan migrate --seed
php artisan storage:link      # makes uploaded recordings playable
```

## 4. Run it
```bash
composer run dev              # or: php artisan serve  +  npm run dev
```
Open http://localhost:8000, create an account, then make yourself an admin:
```bash
php artisan app:make-admin you@example.com
```
The **Admin** link appears in the menu.

## 5. Tests
```bash
php artisan test
```

## How content works
- **Stages** are places in Ganda life (Ewaka, Mu ffumbiro, Mu katale…).
- **Phrases** are Luganda words/sentences with English meaning, notes and recordings (normal + slow).
- **Lessons** are an ordered list of phrases. Exercises (learn, choose, recall, listen, build) are generated automatically, so editors never write quiz questions.
- **Dictionary** holds words and proverbs (engero); one of each is featured daily.
- Finishing a lesson adds its phrases to the learner's **review** queue (spaced repetition), awards **cowries** and grows the **streak**.

## Before launch
All seeded Luganda (Stage 1, 33 words, 2 proverbs) is a **draft**: every phrase shows
"needs review" in the admin. Have a native speaker check each one, tick
"Checked by a native Luganda speaker", and upload recordings.

## Main files
- `app/Services/LessonPlan.php`: builds exercises from a lesson's phrases
- `app/Services/Progress.php`: cowries, streaks, review scheduling
- `app/Models/ReviewItem.php`: spaced-repetition rule
- `resources/views/lesson.blade.php`: the lesson player (Alpine.js)
- `database/seeders/LugandaCourseSeeder.php`: starter content

## Voices on/off

Voice recordings are **switched off** for now (`LUGANDA_VOICE=false`, the default).
While off: no recordings play, there are no listening questions or record-yourself
parts, Mukasa and Nakato talk silently (bubbles and moving mouths), and the Studio is
hidden from the menu. Recordings already saved are kept.

To switch voices back on, add this line to `.env`, then refresh:
```
LUGANDA_VOICE=true
```
(If you ever run `php artisan config:cache`, run it again after changing `.env`.)

## Recording and checking Luganda (native speakers)

Run `php artisan migrate` after updating.

### Give someone access
1. They create a normal account in the app.
2. In **Admin → People** click **Make reviewer**, or run
   `php artisan app:make-reviewer their@email.com`.
3. They now see **🎙️ Studio** and **✓ Check** in the menu (admins see them too).

### Recording in the browser (easiest)
**Studio** shows one phrase at a time in lesson order, starting with those missing audio.
- Tap the red button, say the phrase once, tap again to stop. Listen, then **Save** or **Redo**.
- Phrases have two takes: **Normal speed** and **Slowly**.
- Keyboard: Space = record/stop, Enter = save, → = next.
- Works on phones (Chrome on Android, Safari on iPhone). The browser asks for microphone
  permission the first time. Note: browsers only allow the microphone on `https://` sites
  or `localhost`, so online it must run on HTTPS.

### Recording elsewhere (studio, WhatsApp voice notes)
1. **Studio → Recording script** (`/studio/script`): print it or download the CSV.
   Every phrase has a code such as `P0007`; dictionary words use `W0012`.
2. Name each file starting with its code: `P0007-wasuze-otya.mp3`, and add `slow`
   for the slow take: `P0007-wasuze-otya-slow.mp3`.
3. **Bulk upload** (`/studio/upload`): drop all the files in. Each is matched by its code
   and becomes the new take, waiting to be checked. Unmatched files are listed so you can rename them.

PHP's default limit is 2 MB per file. Phone recordings of a phrase are far smaller, but
for WAV files raise it in php.ini: `upload_max_filesize = 10M` and `post_max_size = 12M`.

### Checking the Luganda
**Check** lists everything still to review, in lesson order.
- Fix the spelling, meaning, literal meaning or usage notes directly in the boxes.
- **Looks right** approves it (with any fixes). **Flag a problem** sends a note to the admin.
- Admins see flagged items in **Admin → Phrases → Flagged by reviewer**; ticking
  "Checked by a native Luganda speaker" there approves it and clears the flag.
- **Admin → People** shows how many phrases each person has recorded and checked.

## Audio: how recordings are approved (since Phase 1)

Run `php artisan migrate` after updating. The migration copies every existing recording
into the new `audio_clips` table: recordings of an approved phrase arrive approved, the
rest arrive "waiting to be checked", and the recordings on **Gyendi** arrive **flagged**
(a test recording is known to have landed there by mistake). Nothing is moved or deleted.

- **The text and each recording are approved separately.** Learners hear a recording only
  when the Luganda text *and* that recording are both approved, and voices are on.
- **Check → 🔊 Recordings to check** lists every recording waiting for a reviewer.
  For each one: **✓ Sounds right**, **⚑ Flag** (say what's wrong) or **✕ Reject** (never play it).
- **A new take never silences a good one.** The approved take keeps playing until the new
  one is approved; then the old one is retired. Old files are kept on disk as history.
- **Re-spelling a phrase** sends its approved recordings back for checking (changing only
  capitals or punctuation doesn't).
- **Same text, one recording.** If dictionary "emu" has no recording, the approved recording
  of lesson phrase "Emu" is used.
- Human recordings are always preferred over generated ones (generation comes in Phase 2).
- Reviewers see a small **pending** badge next to recordings learners can't hear yet, and
  **🔇 no audio** where there is nothing to play. Learners see neither.

Commands:
```bash
php artisan app:audio-report                 # per lesson: approved / pending / FLAGGED / - (missing)
php artisan app:audio-report --lesson=ennamba-1-5 --words
php artisan app:import-legacy-audio          # re-run the import of old recordings (safe to repeat)
```

## Generated audio (since Phase 2)

Run `php artisan migrate` after updating. Settings in `.env`:
```
LUGANDA_TTS=sunbird              # or "fake" (test beeps, free) while trying things out
SUNBIRD_API_TOKEN=...            # from https://api.sunbird.ai/keys
SUNBIRD_VOICE=salt_lug_0001      # optional: the default voice
LUGANDA_TTS_DAILY_LIMIT=50       # optional: safety limit per day (test beeps don't count)
```

**Nothing is generated automatically.** An admin chooses what to generate:
- **Admin → 🤖 Generate audio**: pick a lesson, tick phrases or words, choose a voice, press
  **Generate**. Items go one at a time with a result per row. **Try every voice on 1 item**
  makes one take per Sunbird voice so you can compare them.
- Or from the terminal:
  ```bash
  php artisan app:tts-voices                                          # list Sunbird's Luganda voices
  php artisan app:generate-audio --lesson=ennamba-1-5 --dry-run       # see what would happen
  php artisan app:generate-audio --lesson=ennamba-1-5                 # generate (default limit 10)
  php artisan app:generate-audio --code=P0001 --voice=waxal_lug_0003  # one item, a chosen voice
  ```

Every generated recording is **waiting to be checked**: listen on **Check → 🔊 Recordings to
check** and press **Sounds right**, **Flag** or **Reject**. Approving one voice retires the other
tries for that item. Test beeps can never be approved.

Built-in protections: items with an approved human recording are skipped; the same text + voice
is never generated twice (tick "Generate again" to override); a rejected take isn't retried
silently; the daily limit stops runaway use; every attempt is logged in `audio_generation_requests`.
Sunbird's audio links expire after ~30 minutes, so the app downloads and keeps its own copy
(`storage/app/public/audio/generated/`).

## Audio-first (8 Oct 2026)
- `LUGANDA_PLAY_UNCHECKED=true`: learners hear Sunbird's voice straight away, marked 🤖, even
  before a native speaker checks it. Flagged/rejected recordings never play; a human recording
  (if anyone makes one) always wins. Set it to `false` to go back to "approved only".
- `LUGANDA_TTS_AUTO=true`: saving a new phrase/word (Admin) or re-spelling one (Check page)
  generates its recording automatically in the background.
- Voice the whole course in one go: `php artisan app:generate-audio --all --limit=500`
  (safe to repeat: anything that already has audio is skipped), or press
  **🔊 Give everything a voice** on Admin → Generate audio.
- Recording yourself in the Studio is optional.

## Lesson engine (Phase 3)
Run `php artisan migrate` after updating.

Lessons now come in two kinds:
- **auto** (all lessons so far): exercises are generated from the lesson's phrases, exactly as before.
- **scripted**: the lesson plays its own list of steps (`lesson_steps`), written in an add-only seeder:
  ```php
  $this->addSteps($lesson, [
      ['intro',    ['say' => 'Today we are learning numbers!', 'character' => 'mukasa']],
      ['teach',    ['phrase' => 'Emu', 'picture' => ['name' => 'cowrie', 'count' => 1], 'say' => 'One cowrie: Emu.']],
      ['choose',   ['phrase' => 'Emu', 'feedback' => ['correct' => 'Yes!']]],
      ['sequence', ['sequence' => ['Emu', 'Bbiri', 'Ssatu', 'Nnya', 'Ttaano'], 'ask' => 0]],  // "What comes after Emu?"
      ['match',    ['pairs' => ['Emu', 'Bbiri', 'Ssatu']]],
      ['reward',   ['say' => 'Kulika!']],
  ]);
  ```
  Activities: intro, learn/teach, choose, recall, listen (left out until there is audio), build,
  match, sequence, dialogue, converse, reward. Pictures: cowrie, matooke, mango, egg, cup, ball,
  drum, star, house, person (1–10 of them). Steps refer to phrases by their Luganda text; new
  Luganda is created as a draft for the Check page.
- `php artisan app:lesson-check [slug]` lists every step and any problems. A broken step is
  skipped for learners, and a scripted lesson with nothing playable falls back to automatic.
- Code: `app/Lessons/` (one class per activity), views in `resources/views/lesson/steps/`,
  player in `resources/js/lesson-player.js`.

## Numbers 1–5 pilot (Phase 5)
Turn on the first interactive lesson (add-only, safe to repeat):
```bash
php artisan db:seed --class=LugandaNumbersPilotSeeder
```
"Ennamba 1–5" becomes a 19-step lesson taught by Mukasa, marked 🎬 Interactive on the course path:
introduction → each number with a big numeral, cowries to tap and count, and its recording →
find the picture → what comes after → listen → counting game → match → Mukasa and Nakato
count together → count with Mukasa yourself → reward. It uses only existing phrases (Emu, Bbiri,
Ssatu, Nnya, Ttaano, Oli otya?), so no new Luganda needs checking.

To compare with the old version: `php artisan app:lesson-mode ennamba-1-5 auto` (steps are kept);
back again with `php artisan app:lesson-mode ennamba-1-5 scripted`.
New activity: `find` (tap the picture). New step extras: `figure` (a big numeral), `count_along`
(tap each picture to count), `hide_word` (picture-only questions).

## More interactive lessons (Phase 6)
No migrations. Turn on 14 more classroom lessons (add-only, safe to repeat):
```bash
php artisan db:seed --class=LugandaClassroomLessonsSeeder
php artisan app:lesson-check                 # every scripted lesson: each step "ok"
php artisan app:generate-audio --all --limit=500   # voice the new draft phrases (skips anything with audio)
```
Converted, built like the Numbers pilot (teacher lines in English): Greetings, Morning and evening, Names and respect,
Useful little words, People at home, Food, At the table, Drinks, In the house, Numbers 6–10, Buying, Market goods.
Two new lessons: **A first conversation** (end of Stage 1, only existing phrases) and **Actions · Come, sit, eat**
(Stage 2). New pictures: sun, moon, man, woman, child, door, window, chair, table, bed, beans, rice, meat,
sweet-potato, cassava, orange, tomato, onion, groundnut, bag, water, milk, juice.

**New Luganda to check** (drafts on the Check page, under their lessons): Nze Mukasa, Nze Nakato (Names and respect);
Genda, Tuula, Yimirira, Lya, Nywa, Nywa amazzi, Tuula ku ntebe (Actions).

One lesson at a time: `php artisan db:seed --class="Database\Seeders\Lessons\FoodLesson"`.
Back to automatic for any of them: `php artisan app:lesson-mode emmere auto` (steps are kept).
If a phrase was re-spelled on the Check page, that lesson is skipped with a warning and nothing is changed:
update the spelling in its file in `database/seeders/Lessons/` and run the seeder again.
How to write the next one: `claude/luganda-app-overview.md`.

## Speaking practice (Phase 7)
Run `php artisan migrate` after updating (adds the `speech_attempts` table).

- **Say it** on every new phrase, in every lesson: 🎙️ Record (stops by itself after 7 seconds),
  ▶ My voice, 🔁 Compare (the recording, then you), then **Did it sound the same?** 👍 / 🔁.
  Nothing is graded: speaking is practice, and some learners have no microphone.
- **New lesson step `speak`** ("Your turn to speak") for scripts: `['speak', ['phrase' => 'Ssente', 'say' => '…']]`.
  Left out while voices are off or the phrase has no recording yet. (Existing scripted lessons are not changed.)
- **New game: Say it · Speaking practice** (Games): six phrases you know, say each one, check yourself.
  2 cowries for playing + 1 per "sounded the same" (the daily game cap still applies). Shown only while voices are on.
- **What is saved:** one row per try in `speech_attempts`: who, which phrase, where (learn / speak / game, lesson),
  their verdict, how long they spoke. **The learner's own audio is never stored.**
- **Which phrases are hard to say:** `php artisan app:speaking-report [--days=30]`.

### Automatic checking (off unless you switch it on)
```
LUGANDA_STT=sunbird              # uses your SUNBIRD_API_TOKEN; "off" (default) or "fake" (test listener)
LUGANDA_STT_DAILY_LIMIT=100      # all learners together
LUGANDA_STT_PER_LEARNER_HOURLY=30
```
When on, each recording is sent once to Sunbird's Luganda speech-to-text, the app shows
"We heard: …" with Very close / Nearly there / Not quite yet, and the recording is thrown away.
Learners are told this under the record button. Speech recognition for Luganda can be wrong, so it is
only a hint: the learner's own 👍 / 🔁 still decides, and lessons are never marked on it.
The browser's built-in speech recognition is not used: it cannot promise Luganda, Firefox doesn't
have it, and Chrome sends the audio to Google.

## Polish and going online (Phase 8)
No migrations.

**For reviewers and admins**
- **Check** shows 25 items a page (with ← Previous / Next →), so it stays quick as the course grows.
- **Search** on Check and in the Studio: type Luganda, English or a code (`P0008`, `W0012`).
- **🎙️ Record** on each Check card opens the Studio straight at that phrase.
- The Studio loads 50 phrases at a time; **Next →** at the end of a batch loads the next 50.

**Safety built in**
- Login: after 5 wrong passwords in a minute for one email, it says "Too many tries. Wait N seconds".
- Registering is limited to 20 a minute per connection; new guest accounts to `LUGANDA_GUESTS_PER_HOUR` (100) per connection.
- Every page sends safe headers (no sniffing, no framing by other sites, microphone only for this site;
  HTTPS-only once the site is on HTTPS in production).
- Friendly error pages (not found, not allowed, page expired, too many tries, something went wrong, back soon).
- Accessibility: every page and lesson step passes an automated WCAG 2.1 AA check (axe), at phone width.
- Recordings and built files are cached by browsers for a year (`public/.htaccess`, Apache `mod_headers`):
  every recording gets a new file name, so nobody ever hears an old take.

### Before the site goes online
```bash
php artisan app:production-check     # lists what still needs fixing, with how
```
1. **HTTPS** is required: browsers only allow the microphone (Studio, speaking practice) on HTTPS.
   With Apache: `sudo apt install certbot python3-certbot-apache && sudo certbot --apache`.
2. In `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`.
3. Apache: the site's `DocumentRoot` must be `…/myapp/public` with `AllowOverride All`;
   `sudo a2enmod rewrite headers && sudo systemctl reload apache2`.
4. Files: `npm run build` (and stop `npm run dev`, which leaves `public/hot` behind), `php artisan storage:link`,
   `sudo chown -R www-data:www-data storage bootstrap/cache`.
5. Speed: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
   (run them again after every update; `php artisan optimize:clear` undoes them).
6. Scheduler (prunes old guest accounts weekly), in `sudo crontab -u www-data -e`:
   `* * * * * cd /var/www/html/language/myapp && php artisan schedule:run >> /dev/null 2>&1`
7. Backups, daily: the database (`pg_dump luganda > luganda-$(date +%F).sql`) and the recordings
   (`storage/app/public`). Keep a copy off the server.
8. Recommended: put the code under git (`git init && git add -A && git commit -m "Phase 8"`), so every
   update is one change you can look at and undo. `.env` and `vendor/` are already in `.gitignore`.
