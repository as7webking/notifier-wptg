document.addEventListener("DOMContentLoaded", function () {
  const button = document.querySelector("#select_sound");
  const input = document.querySelector("#telegram_sound_url");
  if (!button || !input || typeof wp === "undefined" || !wp.media) return;

  let fileFrame;

  button.addEventListener("click", function (e) {
    e.preventDefault();

    if (fileFrame) {
      fileFrame.open();
      return;
    }

    fileFrame = wp.media({
      title: notifierWPTG.selectTitle,
      button: { text: notifierWPTG.useText },
      library: { type: "audio" },
      multiple: false,
    });

    fileFrame.on("select", function () {
      const attachment = fileFrame.state().get("selection").first().toJSON();
      input.value = attachment.url;
    });

    fileFrame.open();
  });
});
