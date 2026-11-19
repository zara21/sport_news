document.addEventListener('DOMContentLoaded', () => {
    const enableDragScroll = (scroller) => {
        let pointerId = null;
        let startX = 0;
        let startScrollLeft = 0;
        let dragged = false;
        let suppressClick = false;

        scroller.classList.add('drag-scroll');
        scroller.addEventListener('dragstart', (event) => event.preventDefault());

        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0) return;
            pointerId = event.pointerId;
            startX = event.clientX;
            startScrollLeft = scroller.scrollLeft;
            dragged = false;
            suppressClick = false;
        });

        scroller.addEventListener('pointermove', (event) => {
            if (pointerId !== event.pointerId) return;
            const distance = event.clientX - startX;
            if (!dragged && Math.abs(distance) < 6) return;
            if (!dragged && !scroller.hasPointerCapture(pointerId)) {
                scroller.setPointerCapture(pointerId);
            }
            dragged = true;
            suppressClick = true;
            scroller.classList.add('drag-scroll--active');
            scroller.scrollLeft = startScrollLeft - distance;
            event.preventDefault();
        });

        const stopDragging = (event) => {
            if (pointerId !== event.pointerId) return;
            if (scroller.hasPointerCapture(pointerId)) {
                scroller.releasePointerCapture(pointerId);
            }
            pointerId = null;
            scroller.classList.remove('drag-scroll--active');
            if (dragged) {
                window.setTimeout(() => { suppressClick = false; }, 0);
            }
        };

        scroller.addEventListener('pointerup', stopDragging);
        scroller.addEventListener('pointercancel', stopDragging);
        scroller.addEventListener('click', (event) => {
            if (!suppressClick) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    };

    document.querySelectorAll('.story-feed__container, .home-match-grid, .video-scroll-container')
        .forEach(enableDragScroll);

    document.querySelectorAll('[data-news-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.newsTab;
            document.querySelectorAll('[data-news-tab]').forEach((tab) => {
                const active = tab === button;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-news-panel]').forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.newsPanel === target);
            });
        });
    });

    const leagueSelect = document.getElementById('leagueSelect');
    const standingsBody = document.getElementById('standingsBody');

    const rankClass = (position, total) => {
        if (position <= 4) return 'is-ucl';
        if (position === 5 || position === 6) return 'is-europa';
        if (position > total - 3) return 'is-relegation';
        return '';
    };

    const loadStandings = async (league) => {
        if (!standingsBody) return;
        standingsBody.innerHTML = '<tr><td colspan="4">იტვირთება...</td></tr>';
        try {
            const response = await fetch(`/sport_blog/views/standings.php?league=${encodeURIComponent(league)}`);
            if (!response.ok) throw new Error('standings');
            const rows = await response.json();
            standingsBody.replaceChildren();

            if (!Array.isArray(rows) || rows.length === 0) {
                standingsBody.innerHTML = '<tr><td colspan="4">მონაცემები არ მოიძებნა.</td></tr>';
                return;
            }

            rows.forEach((row) => {
                const tr = document.createElement('tr');
                const positionCell = document.createElement('td');
                const rank = document.createElement('span');
                rank.className = `home-rank ${rankClass(Number(row.position), rows.length)}`;
                rank.textContent = row.position;
                positionCell.append(rank);

                const teamCell = document.createElement('td');
                if (row.articles_url) {
                    const teamLink = document.createElement('a');
                    teamLink.className = 'home-standings__team-link';
                    teamLink.href = row.articles_url;
                    teamLink.textContent = row.team ?? '';
                    teamCell.append(teamLink);
                } else {
                    teamCell.textContent = row.team ?? '';
                }

                [positionCell, teamCell, row.played, row.points].forEach((value) => {
                    if (value instanceof HTMLElement) {
                        tr.append(value);
                    } else {
                        const td = document.createElement('td');
                        td.textContent = value ?? '';
                        tr.append(td);
                    }
                });
                standingsBody.append(tr);
            });
        } catch (error) {
            standingsBody.innerHTML = '<tr><td colspan="4">ცხრილი ვერ ჩაიტვირთა.</td></tr>';
        }
    };

    if (leagueSelect) {
        loadStandings(leagueSelect.value);
        leagueSelect.addEventListener('change', () => loadStandings(leagueSelect.value));
    }

    document.querySelectorAll('[data-scroll-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.scrollTarget);
            const direction = Number(button.dataset.scrollDirection) || 1;
            target?.scrollBy({ left: direction * 520, behavior: 'smooth' });
        });
    });

    document.querySelectorAll('.home-match-grid').forEach((grid) => {
        grid.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            grid.scrollBy({ left: event.key === 'ArrowRight' ? 280 : -280, behavior: 'smooth' });
        });
    });

    const loadMoreMatches = document.getElementById('loadMoreMatches');
    loadMoreMatches?.addEventListener('click', async () => {
        if (loadMoreMatches.disabled) return;
        loadMoreMatches.disabled = true;
        const offset = Number(loadMoreMatches.dataset.offset) || 0;
        try {
            const response = await fetch(`/sport_blog/views/matches_more.php?offset=${offset}&limit=12`);
            if (!response.ok) throw new Error('matches');
            const data = await response.json();
            document.getElementById('matchGrid')?.insertAdjacentHTML('beforeend', data.html || '');
            const nextOffset = offset + Number(data.count || 0);
            loadMoreMatches.dataset.offset = String(nextOffset);
            if (!data.count || nextOffset >= Number(data.total || loadMoreMatches.dataset.total)) {
                loadMoreMatches.remove();
            } else {
                loadMoreMatches.disabled = false;
            }
        } catch (error) {
            loadMoreMatches.disabled = false;
        }
    });
});
