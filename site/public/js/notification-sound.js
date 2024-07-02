/* exported NotificationSound */
class NotificationSound {
    constructor() {
        this.sound = document.createElement('audio');

        if (this.sound.canPlayType('audio/mpeg') === '') {
            throw 'Unable to produce notification sounds.  Browser doesn\'t support mp3 audio files.';
        }

        this.sound.src = 'quack-alert.mp3';
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
