chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
  let isAgarz = false;
  try {
    const url = tabs[0]?.url ? new URL(tabs[0].url) : null;
    isAgarz = url?.hostname === 'agarz.com' || url?.hostname?.endsWith('.agarz.com');
  } catch (e) {
    isAgarz = false;
  }
  const status = document.getElementById('status');

  if (isAgarz) {
    status.textContent = '✓ Agarz Aktif';
    status.classList.add('active');
  }

  document.getElementById('open').addEventListener('click', () => {
    chrome.tabs.sendMessage(tabs[0].id, { action: 'toggle' });
    window.close();
  });
});
