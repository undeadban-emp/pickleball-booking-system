export default function matchScoreboard(config) {
    return {
        match: config.match,
        game: config.game,
        events: config.events,
        urls: config.urls,
        csrfToken: config.csrfToken,

        historyIndex: null, // null = live/current. -1 = before any events (game start). Otherwise an index into `events`.
        loading: false,
        errorMessage: null,
        pendingWinnerTeam: null,

        init() {
            this.pendingWinnerTeam = this.computePendingWinner();
        },

        get isLive() {
            return this.historyIndex === null;
        },

        // What the court/score header should currently render — either the
        // live game state, or a read-only snapshot from rally history.
        get displayed() {
            if (this.isLive || this.events.length === 0) {
                return {
                    team1_score: this.game.team1_score,
                    team2_score: this.game.team2_score,
                    serving_team: this.game.serving_team,
                    server_position: this.game.server_position,
                    server_number: this.game.server_number,
                };
            }

            if (this.historyIndex === -1) {
                return {
                    team1_score: 0,
                    team2_score: 0,
                    serving_team: this.game.starting_serving_team,
                    server_position: this.game.starting_server_position,
                    server_number: this.game.starting_server_number,
                };
            }

            const e = this.events[this.historyIndex];
            return {
                team1_score: e.team1_score_after,
                team2_score: e.team2_score_after,
                serving_team: e.serving_team_after,
                server_position: e.server_position_after,
                server_number: e.server_number_after,
            };
        },

        // Standard pickleball score-calling order: serving team's score first,
        // then the receiving team's, then the server number — not simply
        // "team 1 - team 2", since either team can be the one serving.
        // Rally-point never announces a server number (no "Server 1/2" concept
        // when every rally scores) — just the two scores.
        get scoreLabel() {
            const servingScore = this.displayed.serving_team === 1 ? this.displayed.team1_score : this.displayed.team2_score;
            const receivingScore = this.displayed.serving_team === 1 ? this.displayed.team2_score : this.displayed.team1_score;

            if (this.match.scoring_type === 'rally') {
                return `${servingScore} - ${receivingScore}`;
            }

            return `${servingScore} - ${receivingScore} - ${this.displayed.server_number}`;
        },

        // Court side (1=right/2=left) per player id, for whatever moment is
        // currently being viewed — live, or a historical rally-history snapshot.
        // Live reads straight off match.players (kept fresh by applyPayload());
        // historical reads the event's own frozen snapshot, since a team's
        // arrangement keeps rotating after that point in time.
        get currentPositions() {
            if (!this.isLive && this.events.length > 0) {
                return this.historyIndex === -1
                    ? (this.game.starting_player_positions ?? {})
                    : (this.events[this.historyIndex].player_positions_after ?? {});
            }

            const map = {};
            this.match.players.forEach((p) => { map[p.id] = p.position; });

            return map;
        },

        // Sorted by CURRENT court side (rotates during play) so the DOM order
        // itself swaps — this is what makes the swapped players visually trade
        // quadrants instead of just changing a highlight. Team 1's column reads
        // top-to-bottom as Left-then-Right (descending, position 2 before 1);
        // Team 2's reads Right-then-Left (ascending) — mirrored, matching the
        // court view. Same rule for both scoring types — rally-point's team 1
        // positions are static (never rotate mid-game), but they still need to
        // land Left-on-top / Right-on-bottom.
        teamPlayers(team) {
            const positions = this.currentPositions;
            const direction = team === 1 ? -1 : 1;

            return this.match.players
                .filter((p) => p.team === team)
                .map((p) => ({ ...p, position: positions[p.id] ?? p.position }))
                .sort((a, b) => direction * (a.position - b.position));
        },

        // Sorted by stable slot (not current side) so the team name label
        // doesn't reorder mid-match every time the players swap sides.
        teamName(team) {
            return this.match.players
                .filter((p) => p.team === team)
                .sort((a, b) => a.slot - b.slot)
                .map((p) => p.name)
                .join(' / ');
        },

        isServing(player) {
            if (this.isLive && this.game.status !== 'in_progress') return false;

            return player.team === this.displayed.serving_team
                && (this.currentPositions[player.id] ?? player.position) === this.displayed.server_position;
        },

        timeoutsUsed(team) {
            return team === 1 ? this.game.team1_timeouts_used : this.game.team2_timeouts_used;
        },

        get canScore() {
            return this.isLive && this.match.status === 'in_progress' && this.game.status === 'in_progress' && this.pendingWinnerTeam === null;
        },

        canTimeout(team) {
            return this.canScore && this.timeoutsUsed(team) < this.match.timeouts_per_game;
        },

        // Display-only mirror of MatchScoringService::checkGameWin — decides
        // when to show the "Game Completed" modal. The server remains the
        // authority; completeGame() re-validates before persisting anything.
        computePendingWinner() {
            const target = this.match.games_to;
            const s1 = this.game.team1_score;
            const s2 = this.game.team2_score;
            const leader = Math.max(s1, s2);
            const trailer = Math.min(s1, s2);

            if (leader < target) return null;
            if (this.match.win_rule === 'win_by_2' && leader - trailer < 2) return null;

            return s1 > s2 ? 1 : 2;
        },

        async post(url, body = {}) {
            this.loading = true;
            this.errorMessage = null;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(body),
                });

                const payload = await res.json();

                if (!res.ok) {
                    this.errorMessage = payload.message ?? 'Something went wrong.';
                    return null;
                }

                return payload;
            } catch (e) {
                this.errorMessage = 'Could not reach the server. Please try again.';
                return null;
            } finally {
                this.loading = false;
            }
        },

        applyPayload(payload) {
            if (!payload) return;

            this.match = { ...this.match, ...payload.match };
            this.game = payload.game;
            this.events = payload.events ?? this.events;
            this.historyIndex = null;
            this.pendingWinnerTeam = 'pending_winner_team' in payload ? payload.pending_winner_team : this.computePendingWinner();
        },

        // Route templates carry a `__GAME_ID__` placeholder (see matches/show.blade.php)
        // because the live game's id changes as the match progresses from game to
        // game — resolve against whichever game is current at call time.
        gameUrl(key) {
            return this.urls[key].replace('__GAME_ID__', this.game.id);
        },

        // Every action below checks + the `loading` flag is set (inside post())
        // synchronously as the very first thing that happens, before any await —
        // so a double-tap that fires two click events back to back can't slip a
        // second request in while the first is still in flight.
        async point() {
            if (this.loading || !this.canScore) return;
            this.applyPayload(await this.post(this.gameUrl('point')));
        },

        async sideOut() {
            if (this.loading || !this.canScore) return;
            this.applyPayload(await this.post(this.gameUrl('sideOut')));
        },

        async timeoutFor(team) {
            if (this.loading || !this.canTimeout(team)) return;
            this.applyPayload(await this.post(this.gameUrl('timeout'), { team }));
        },

        async confirmGameComplete() {
            if (this.loading) return;

            const payload = await this.post(this.gameUrl('completeGame'));
            if (!payload) return;

            if (payload.match_completed) {
                // Match Results is a server-rendered partial (matches.status
                // flipped to "verifying") — simplest to let the next load render it.
                window.location.href = this.urls.show;
                return;
            }

            const nextGame = [...payload.match.games].sort((a, b) => b.game_number - a.game_number)[0];

            this.match = payload.match;
            this.game = nextGame;
            this.events = [];
            this.historyIndex = null;
            this.pendingWinnerTeam = null;
        },

        historyBack() {
            if (this.events.length === 0) return;
            // -1 is the earliest reachable point: "before any events" (game start).
            this.historyIndex = this.historyIndex === null ? this.events.length - 1 : Math.max(-1, this.historyIndex - 1);
        },

        historyForward() {
            if (this.historyIndex === null) return;

            if (this.historyIndex >= this.events.length - 1) {
                this.historyIndex = null;
                return;
            }

            this.historyIndex += 1;
        },

        resumePlay() {
            this.historyIndex = null;
        },

        // Mid-game "undo to here" — only available while reviewing a specific
        // point in Rally History. historyIndex -1 (game start) rewinds to
        // sequence 0 — the same operation, just at the earliest possible
        // point, not a separate "restart the whole game" action.
        async undoToHere() {
            if (this.loading || this.historyIndex === null) return;

            const sequence = this.historyIndex === -1 ? 0 : this.events[this.historyIndex].sequence;
            if (!window.confirm('Undo every action after this point? This cannot be undone.')) return;

            this.applyPayload(await this.post(this.gameUrl('rewind'), { sequence }));
        },
    };
}
