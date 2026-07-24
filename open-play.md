# 🎾 Flexible Open Play System
### Booking System Design (Supports 1 or More Courts)

---

# Overview

The Open Play feature allows registered users to create and join player-organized pickleball sessions. Unlike traditional court booking, players join an Open Play Room where the system automatically creates fair doubles matches and manages player rotation.

The system is designed to support **one or multiple courts** without changing the core logic, making it scalable as the facility grows.

---

# Objectives

- Allow players to organize their own Open Play sessions.
- Automatically generate fair doubles matches.
- Support any number of courts.
- Ensure every player gets similar playing time.
- Minimize repeated partners and opponents.
- Reduce manual work for hosts.

---

# User Roles

## Host

- Creates the Open Play room.
- Selects courts to use.
- Starts the session.
- Ends matches (or allows automatic timers).
- Ends the Open Play session.

## Player

- Creates an account.
- Joins available Open Play rooms.
- Waits for automatic match assignments.
- Views current and upcoming matches.

---

# Open Play Workflow

```text
Login
   │
   ▼
Browse Open Play Rooms
   │
   ▼
Join Room
   │
   ▼
Wait Until Session Starts
   │
   ▼
Host Starts Session
   │
   ▼
System Creates Teams
   │
   ▼
Assign Teams to Available Courts
   │
   ▼
Play Match
   │
   ▼
Match Ends
   │
   ▼
System Creates Next Match
   │
   ▼
Repeat Until Session Ends
```

---

# Create Open Play Room

The host fills in the following information.

| Field | Description |
|---------|-------------|
| Room Name | Friday Night Open Play |
| Date | Session Date |
| Time | Start Time |
| Skill Level | Beginner / Intermediate / Advanced / Any |
| Selected Courts | Court 1, Court 2, etc. |
| Maximum Players | 8 / 12 / 16 / Custom |
| Match Format | First to 11 / Timed |
| Visibility | Public / Private |

Example:

```
Friday Night Open Play

Date
July 30

Time
6:00 PM

Courts

☑ Court 1
☑ Court 2

Maximum Players

16

Skill Level

Intermediate

Visibility

Public
```

---

# Joining a Room

Only registered users may join.

Example:

```
Friday Night Open Play

Players

9 / 16

✓ John
✓ Sarah
✓ Mike
✓ Anna
✓ Chris
✓ David
✓ Kevin
✓ Lisa
✓ Jenny

[ Join Room ]
```

---

# Room Status

```
Waiting
```

↓

```
Ready
```

↓

```
In Progress
```

↓

```
Finished
```

---

# Starting the Session

Only the host can start the session.

Once started:

- Room is locked.
- No additional players can join.
- Matchmaking engine begins.
- Teams are created automatically.

---

# Automatic Team Generation

Every player joins individually.

Example:

```
John
Sarah
Mike
Anna
Chris
David
Kevin
Lisa
Tom
Rose
Mark
Jenny
```

The system automatically creates doubles teams.

Example:

```
Team 1
John + Lisa

Team 2
Sarah + David

Team 3
Mike + Jenny

Team 4
Kevin + Rose

Team 5
Chris + Mark

Team 6
Anna + Tom
```

Players do **not** need to choose a partner.

---

# Court Assignment

The system assigns teams to every available court.

## One Court

```
Court 1

Team 1

VS

Team 2

Waiting

Team 3
Team 4
Team 5
Team 6
```

---

## Two Courts

```
Court 1

Team 1

VS

Team 2

------------------

Court 2

Team 3

VS

Team 4

------------------

Waiting

Team 5
Team 6
```

---

## Three Courts

```
Court 1

Team 1 vs Team 2

Court 2

Team 3 vs Team 4

Court 3

Team 5 vs Team 6
```

The same logic works for any number of courts.

---

# Match Completion

When a court finishes:

```
Finish Match
```

Only that court updates.

Example:

Court 2 finishes while Court 1 is still playing.

```
Court 1

Still Playing

-------------------

Court 2

Loads Next Match Automatically
```

Other courts continue without interruption.

---

# Smart Matchmaking Engine

Instead of random shuffling every round, the matchmaking engine considers several factors.

## Priorities

1. Longest waiting players
2. Fewest games played
3. Avoid previous partners
4. Avoid previous opponents
5. Balance total games played

This creates a fair experience throughout the session.

---

# Waiting Queue

Every player has a status.

```
Playing

Waiting

Finished Match

Next Match
```

The queue updates automatically after every completed match.

---

# Live Dashboard

```
Friday Night Open Play

2 Courts

----------------------

Court 1

John + Lisa

VS

Sarah + David

Status

Playing

----------------------

Court 2

Mike + Jenny

VS

Kevin + Rose

Status

Playing

----------------------

Waiting

Chris

Mark

Anna

Tom
```

---

# Session Summary

When the session ends:

```
Friday Night Open Play

Players

16

Matches Played

18

Duration

2 Hours
```

Each player can view:

```
Games Played

Wins

Losses

Total Play Time
```

---

# Database Structure

## open_play_rooms

| Column |
|----------|
| id |
| host_user_id |
| title |
| date |
| start_time |
| skill_level |
| max_players |
| visibility |
| status |
| created_at |

---

## open_play_room_courts

| Column |
|----------|
| id |
| room_id |
| court_id |

---

## open_play_players

| Column |
|----------|
| id |
| room_id |
| user_id |
| games_played |
| wins |
| losses |
| waiting_order |
| current_status |

---

## open_play_matches

| Column |
|----------|
| id |
| room_id |
| court_id |
| round_number |
| team_a |
| team_b |
| winner |
| status |
| started_at |
| ended_at |

---

# Future Enhancements

- QR Code check-in
- Push notifications when it's your turn
- Match timer (e.g., 15 minutes)
- Skill-based matchmaking
- AI-assisted team balancing
- Waitlist for full rooms
- Match history
- Player ratings
- Achievement badges
- Live scoreboard
- Mobile app support

---

# Advantages

✅ Supports one or multiple courts

✅ Fully automated matchmaking

✅ Fair player rotation

✅ No manual pairing required

✅ Easy for players to join

✅ Minimal work for hosts

✅ Scalable architecture

---

# Recommended Future Feature

Implement an advanced **Matchmaking Engine** instead of simple random shuffling.

The engine should:

- Avoid pairing the same teammates repeatedly.
- Minimize repeat opponents.
- Prioritize players who have waited the longest.
- Keep the number of games played as balanced as possible.
- Support any number of active courts.

This approach provides a much better player experience than traditional Open Play systems and will make your booking platform stand out from many existing pickleball management solutions.