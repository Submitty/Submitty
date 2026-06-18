/* exported NotificationSound */
class NotificationSound {
    constructor() {
        this.sound = document.getElementById('quack-alert.mp3');

        if (this.sound.canPlayType('audio/mpeg') === '') {
            throw 'Unable to produce notification sounds.  Browser doesn\'t support mp3 audio files.';
        }
    }

    async play() {
        try {
            await this.sound.play();
        }
        catch (error) {
            console.error('Failed to play the notification sound: ', error);
        }
    }
}
