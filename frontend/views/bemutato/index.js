(() => {
  const header = document.querySelector('.sales-header');

  if (header) {
    const updateHeader = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
  }

  const videoFrame = document.querySelector('.sales-video-consent');

  if (videoFrame) {
    const loadButton = videoFrame.querySelector('.sales-video-load');

    loadButton?.addEventListener('click', () => {
      const videoUrl = videoFrame.dataset.videoUrl;
      if (!videoUrl) return;

      const iframe = document.createElement('iframe');
      iframe.src = videoUrl;
      iframe.title = 'Olcsi Business időpontfoglaló bemutató videó';
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

      videoFrame.replaceChildren(iframe);
    });
  }

  const revealItems = [...document.querySelectorAll('[data-reveal]')];
  if (!revealItems.length) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, {
    rootMargin: '0px 0px -8% 0px',
    threshold: 0.08,
  });

  revealItems.forEach((item) => observer.observe(item));
})();
