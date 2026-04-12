chrome.runtime.onInstalled.addListener(() => {
  chrome.storage.local.set({
    agarz_v2: {
      autoMode: false,
      showOverlay: true,
      showRadar: true
    }
  });
});
