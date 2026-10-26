(function () {
    var button = document.querySelector('[data-scan]');
    var form = document.querySelector('[data-scan-form]');
    var note = document.getElementById('scan-support');

    if (!button || !form) {
        return;
    }

    if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
        if (note) {
            note.hidden = false;
        }

        return;
    }

    button.addEventListener('click', function () {
        var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
            var video = form.querySelector('[data-preview]');

            if (!video) {
                stream.getTracks().forEach(function (track) { track.stop(); });

                return;
            }

            video.hidden = false;
            video.srcObject = stream;

            return video.play().then(function () {
                return detector.detect(video);
            }).then(function (codes) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                video.hidden = true;

                if (!codes || !codes[0] || !codes[0].rawValue) {
                    if (note) {
                        note.hidden = false;
                        note.textContent = 'No code was found. Type the ticket code.';
                    }

                    return;
                }

                var input = form.querySelector('[name="token"]');

                if (input) {
                    input.value = codes[0].rawValue;
                    form.requestSubmit();
                }
            });
        }).catch(function () {
            if (note) {
                note.hidden = false;
                note.textContent = 'The camera is unavailable. Type the ticket code.';
            }
        });
    });
}());
