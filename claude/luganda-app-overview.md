# Luganda learning app: overview

Laravel 13 · PostgreSQL · Blade + Alpine.js · Tailwind 4. Project root: `/var/www/html/language/myapp`.
Setup and day-to-day commands for every phase are in `SETUP-LUGANDA.md`; this file is the map.

> Note (16 Oct 2026): this file and `claude/classroom-plan.md` were not on disk when Phase 6
> started, so this overview was rebuilt from the code and SETUP-LUGANDA.md. The original
> phase plan (`classroom-plan.md`) is still missing; re-add it if you have a copy.

## Where things are

| Area | Main files |
|---|---|
| Course content (add-only seeders) | `database/seeders/` (`LugandaCourseSeeder`, `LugandaKitchenMarketSeeder`, `LugandaNumbersPilotSeeder`, `LugandaClassroomLessonsSeeder` + `Lessons/`), helpers in `Concerns/AddsCourseContent.php` |
| Lesson engine | `app/Lessons/` (one class per activity, `ActivityRegistry`, `ScriptedLesson`, `LessonContext`, `Pictures`), `app/Services/LessonPlan.php` |
| Lesson player | `resources/views/lesson.blade.php`, steps in `resources/views/lesson/steps/`, `resources/js/lesson-player.js`, pictures in `resources/js/pictures.js` |
| Conversations (Mukasa & Nakato) | `app/Support/Conversations.php` |
| Progress (cowries, streaks, review) | `app/Services/Progress.php`, `app/Models/ReviewItem.php` |
| Audio | `app/Audio/`, `app/Services/AudioGenerator.php`, `AutoAudio.php`, `audio_clips` table |
| Native-speaker tools | Studio (record) and Check (approve text and recordings): `app/Http/Controllers/Contributor/` |

## Phases so far

- **Phase 0 (base app)**: 7 stages of Ganda life; Stage 1–3 lessons (all Luganda seeded as drafts); automatic exercises
  (learn, choose, recall, listen, build); review queue (spaced repetition); cowries, streaks, games (memory, talking drum);
  guest accounts; phrasebook and dictionary; contributor roles (Studio, Check).
- **Phase 1 (audio approval)**: `audio_clips` table; text and each recording approved separately; a new take never silences a good one.
- **Phase 2 (generated audio)**: Sunbird AI TTS; `app:generate-audio`; daily limit; every attempt logged.
  Audio-first (8 Oct): `LUGANDA_PLAY_UNCHECKED=true` plays Sunbird voices (🤖) before review; `LUGANDA_TTS_AUTO=true`
  voices phrases saved in Admin/Check automatically.
- **Phase 3 (lesson engine)**: lessons are `auto` (generated from phrases) or `scripted` (`lesson_steps`, written with
  `addSteps()` in add-only seeders). `app:lesson-check`, `app:lesson-mode`.
- **Phase 4 (teaching characters)**: Mukasa (stages 1, 3, 5, 7) and Nakato (stages 2, 4, 6) teach in a classroom
  layout with moods (speaking, happy, sad, pointing, celebrating…); Ngoma the drum cheers.
- **Phase 5 (Numbers 1–5 pilot)**: `LugandaNumbersPilotSeeder`, the model for interactive lessons; activity `find`,
  extras `figure`, `count_along`, `hide_word`.
- **Phase 6 (more interactive lessons)**: see below.

## Phase 6: more interactive classroom lessons (16 Oct 2026)

14 more lessons are now scripted like the pilot. Each is one add-only seeder in `database/seeders/Lessons/`,
all run by `LugandaClassroomLessonsSeeder` (also part of `DatabaseSeeder`). 15 interactive lessons in all.

| # | Lesson (slug) | Stage | Focus | New pictures | New Luganda |
|---|---|---|---|---|---|
| 1 | Okulamusa · Greetings (`okulamusa`) | 1 | greet, answer, thank; 2 conversations | – | – |
| 2 | Morning and evening (`enkya-n-akawungeezi`) | 1 | which greeting when; goodbye | sun, moon | – |
| 3 | Names and respect (`amannya`) | 1 | ask/say names; Ssebo/Nnyabo | man, woman | Nze Mukasa, Nze Nakato |
| 4 | Useful little words (`ebigambo-ebikulu`) | 1 | everyday phrases | – | – |
| 5 | People at home (`ab-ewaka`) | 1 | family | child | – |
| 6 | **A first conversation** (`first-conversation`, new, stage 1 #6) | 1 | whole conversations from lessons 1–3 | – | – |
| 7 | Emmere · Food (`emmere`) | 2 | food | sweet-potato, cassava, beans, rice, meat | – |
| 8 | Ku mmeeza · At the table (`ku-mmeeza`) | 2 | a meal as a conversation | water | – |
| 9 | Ebyokunywa · Drinks (`ebyokunywa`) | 2 | drinks; I want / I don't want | milk, juice | – |
| 10 | Mu nnyumba · In the house (`mu-nnyumba`) | 2 | objects at home | door, window, chair, table, bed | – |
| 11 | **Actions · Come, sit, eat** (`actions`, new, stage 2 #5) | 2 | simple commands | – | Genda, Tuula, Yimirira, Lya, Nywa, Nywa amazzi, Tuula ku ntebe |
| 12 | Ennamba 6–10 (`ennamba-6-10`) | 3 | numbers, like the pilot | – | – |
| 13 | Okugula · Buying (`okugula`) | 3 | price, bargaining; learner is the buyer | – | – |
| 14 | Ebintu mu katale · Market goods (`ebintu-mu-katale`) | 3 | market goods | orange, tomato, onion, groundnut, bag | – |

Still automatic: **Ssente · Money** (numbers in tens and thousands: a later phase).

### How a classroom lesson is written
```php
class FoodLesson extends ClassroomLesson            // database/seeders/Lessons/
{
    protected string $slug = 'emmere';
    protected function steps(): array { return [ ['intro', [...]], ['teach', [...]], … ['reward', [...]] ]; }
    protected function newPhrases(): array { return []; }   // Luganda => English or [English, note for the reviewer]
    protected function createLesson(): ?Lesson { return null; } // only for a brand-new lesson (lessonOfExistingPhrases())
}
```
Then list it in `LugandaClassroomLessonsSeeder::LESSONS`. Rules the base class enforces:
- **Add-only.** A lesson that already has steps is never touched (edits are kept).
- **All or nothing.** If a phrase is missing (e.g. re-spelled on the Check page), nothing is saved, the lesson stays
  automatic, and the seeder warns which phrase: update the script to the new spelling and run it again.
- **No silent Luganda.** Steps refer to phrases by their Luganda text. New Luganda only comes from `newPhrases()`,
  is created as a draft (`is_reviewed = false`) with a reviewer note, and is added to the lesson's phrase list so the
  Check page shows it under its lesson. A brand-new lesson's own phrase list may only hold phrases that already exist.
- Teacher lines (`say`, `feedback`) are English; the only Luganda in them is Luganda already in the course.
  `ClassroomLessonsTest` checks this word by word (English words live in `tests/Fixtures/teacher-english-words.txt`).

### Engine changes in Phase 6 (small)
- `addSteps()` runs in a transaction; `$newPhrases` values may be `[English, notes]`; new drafts join the lesson.
- 23 new pictures (`app/Lessons/Pictures.php` + `resources/js/pictures.js`, kept in step by a test); screen-reader
  labels for irregular plurals; a single object on a "find" card is shown larger.
- Dialogue and "Your turn" steps show the script's `say` line as a caption (before, it was hidden there).
- Tests: `seedAutomaticCourse()` in `tests/TestCase.php` seeds the course as before Phase 6, for tests of the automatic
  exercises; `ClassroomLessonsTest` covers the new lessons.

## Key decisions
- Every seeded Luganda phrase is a draft until a native speaker approves it on the Check page; nothing is invented silently.
- Seeders are add-only and safe to repeat; reviewers' corrections always win.
- Teacher talk is English; Luganda appears as taught phrases (with audio).
- Counting forms (Emu, Bbiri…) are taught as such; counting things (ente emu, abantu babiri…) needs noun-class agreement
  and is left for a later lesson, so Market goods does not count goods.
- Learners hear Sunbird voices straight away (🤖); human recordings always win.
- Any scripted lesson can go back to automatic: `php artisan app:lesson-mode <slug> auto` (its steps are kept).

## Next ideas (not started)
- Ssente · Money as a scripted lesson (prices with Omutwalo, Lukumi…).
- Counting things (noun-class agreement), days of the week (a natural `sequence`).
- Stages 4–7 lessons.
