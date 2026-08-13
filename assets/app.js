(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];

  const toggle = $('.signal-toggle');
  const ribbon = $('.signal-ribbon');
  const setRibbon = open => {
    if (!toggle || !ribbon) return;
    toggle.setAttribute('aria-expanded', String(open));
    ribbon.setAttribute('aria-hidden', String(!open));
    ribbon.classList.toggle('open', open);
    document.body.classList.toggle('ribbon-open', open);
    $('.signal-toggle span').textContent = open ? 'Close' : 'Menu';
  };
  toggle?.addEventListener('click', () => setRibbon(toggle.getAttribute('aria-expanded') !== 'true'));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setRibbon(false); });
  $$('.signal-ribbon a').forEach(a => a.addEventListener('click', () => setRibbon(false)));

  const consent = $('.consent');
  const consentKey = 'sfl-consent';
  const savedConsent = localStorage.getItem(consentKey);
  if (savedConsent) consent?.classList.add('hide');
  $$('[data-consent]').forEach(btn => btn.addEventListener('click', () => {
    const allowed = btn.dataset.consent === 'allow';
    localStorage.setItem(consentKey, allowed ? 'granted' : 'denied');
    if (typeof window.gtag === 'function') window.gtag('consent', 'update', {
      analytics_storage: allowed ? 'granted' : 'denied',
      ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'
    });
    consent?.classList.add('hide');
  }));

  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduced && 'IntersectionObserver' in window) {
    const targets = $$('main > section, .journal-wall article, .register-deck article, .long-article > section');
    targets.forEach(el => el.setAttribute('data-reveal', ''));
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
    }), { threshold: .08, rootMargin: '0px 0px -40px' });
    targets.forEach(el => observer.observe(el));
  }

  $('.sound-toggle')?.addEventListener('click', e => {
    const btn = e.currentTarget;
    btn.setAttribute('aria-pressed', String(btn.getAttribute('aria-pressed') !== 'true'));
  });

  const orbits = {
    bright: ['BRIGHT','74','Warm depth + soft grain','Use acidity early, then move toward rounder flavours so the menu develops instead of repeating one sharp note.','acid fatigue','taste after resting'],
    earth: ['EARTH','63','Fresh green + clear acid','Root, mushroom and toasted notes gain energy from herbs, bitterness or a measured fresh finish.','brown repetition','add crisp green'],
    smoke: ['SMOKE','58','Clean fruit + cool cream','Keep smoke to one deliberate component and create space around it with something cool or bright.','palate weight','smell before adding'],
    silk: ['SILK','69','Toast + raw crunch','Creamy and tender courses need a dry, crisp or chewy interruption to keep the sequence active.','soft monotony','hold garnish dry'],
    crunch: ['CRUNCH','77','Broth + tender roast','Use crispness as an accent beside moisture and tenderness rather than making every plate hard work.','dry sequence','check sauce volume']
  };
  $$('[data-orbit]').forEach(btn => btn.addEventListener('click', () => {
    $$('[data-orbit]').forEach(b => b.classList.toggle('active', b === btn));
    const d = orbits[btn.dataset.orbit];
    $('[data-orbit-title]').textContent = d[0]; $('[data-orbit-score]').textContent = d[1];
    $('[data-orbit-counter]').textContent = d[2]; $('[data-orbit-copy]').textContent = d[3];
    $('[data-orbit-watch]').textContent = d[4]; $('[data-orbit-test]').textContent = d[5];
  }));

  const times = {
    early: ['15:00','17:20','18:15','Make dressings, wash leaves and set serving pieces.','Begin slow roasting and bring chilled elements into sequence.','Warm plates, finish herbs and clear the work surface.'],
    prime: ['16:00','18:20','19:15','Complete cold prep and check the table route.','Start the main heat window and portion stable components.','Dress the first course and protect a calm welcome.'],
    late: ['17:00','19:20','20:15','Set sauces, dessert and all non-heated service pieces.','Move roasting and reheating into one controlled equipment window.','Finish only fresh herbs, hot components and the first handoff.']
  };
  $$('[data-time]').forEach(btn => btn.addEventListener('click', () => {
    $$('[data-time]').forEach(b => b.classList.toggle('active', b === btn));
    const d = times[btn.dataset.time];
    $('[data-time-one]').textContent=d[0]; $('[data-time-two]').textContent=d[1]; $('[data-time-three]').textContent=d[2];
    $('[data-time-copy-one]').textContent=d[3]; $('[data-time-copy-two]').textContent=d[4]; $('[data-time-copy-three]').textContent=d[5];
  }));

  const lights = {
    warm: ['WARM FOCUS / 42%','Soft directional warmth, with enough neutral fill to keep greens and sauces legible.'],
    pearl: ['PEARL CLEAR / 61%','A clearer neutral field for detailed plates, balanced with low surrounding light to avoid a flat room.'],
    coral: ['CORAL HOUR / 36%','A coloured edge around the table while neutral light remains on faces, food and walking paths.']
  };
  $$('[data-light]').forEach(btn => btn.addEventListener('click', () => {
    $$('[data-light]').forEach(b => b.classList.toggle('active', b === btn));
    const d = lights[btn.dataset.light];
    $('.spectrum-stage').dataset.spectrum = btn.dataset.light;
    $('[data-spectrum-label]').textContent = d[0]; $('[data-light-copy]').textContent = d[1];
  }));

  $$('[data-filter]').forEach(btn => btn.addEventListener('click', () => {
    const filter = btn.dataset.filter;
    $$('[data-filter]').forEach(b => b.classList.toggle('active', b === btn));
    let count = 0;
    $$('.register-deck article').forEach(card => {
      const show = filter === 'all' || card.dataset.cat.split(' ').includes(filter);
      card.hidden = !show; if (show) count++;
    });
    if ($('[data-result-count]')) $('[data-result-count]').textContent = String(count).padStart(2,'0');
  }));

  $('.contact-signal form')?.addEventListener('submit', e => {
    e.preventDefault();
    $('.form-status').textContent = 'Your message is prepared. Email hello@supperfutureline.com to send it to the studio.';
  });
})();
