/* exported NotificationSound */
class NotificationSound {
    constructor() {
        this.sound = document.createElement('audio');

        if (this.sound.canPlayType('audio/mp3') === '') {
            throw 'Unable to produce notification sounds.  Browser doesn\'t support mp3 audio files.';
        }

        this.sound.src = 'quack-alert.mp3';
    }

    play() {
        this.sound.play();
    }
}
