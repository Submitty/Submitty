/**
 * Wrapper around the builtin WebSocket class. This provides us
 * with two major benefits:
 *  1. The websocket will attempt to reconnect if the connection closes
 *  2. It will automatically perform JSON.parse/JSON.stringify on
 *      incoming/outgoing messages.
 *
 * Example usage would be:
 *
 * const client = WebSocketClient();
 * client.onmessage = (msg) => {
 *   // do something with msg
 * }
 * client.onopen = () => {
 *   client.send({
 *     type: 'getMessages'
 *   });
 * }
 * client.open();
 *
 * Due to the reconnecting strategy, the onopen method will
 * get called on the first connect and then subsequent reconnects.
 */

/* exported WebSocketClient */
class WebSocketClient {
    constructor() {
        this.number = 0;
        // 10 seconds + a random number of seconds between 0 and 10
        this.autoReconnectInterval = (10 + (Math.random() * 11)) * 1000;
        this.onopen = null;
        this.onmessage = null;
        // We do string replacement here so that http -> ws, https -> wss.
        const my_url = new URL(document.body.dataset.baseUrl.replace('http', 'ws'));
        my_url.port = window.websocketPort;
        my_url.pathname = 'ws';
        this.url = my_url.href;
        this.serverError = false;
    }

    open(page, args = {}) {
        console.log(`WebSocket: connecting to ${this.url}`);
        const [term, course] = document.body.dataset.courseUrl.split('/').slice(4);
        const urlWithParams = new URL(this.url);
        urlWithParams.searchParams.append('page', page);
        urlWithParams.searchParams.append('course', course);
        urlWithParams.searchParams.append('term', term);

        // Add websocket token if available
        const wsToken = args.ws_token || window.websocketToken;

        if (wsToken) {
            urlWithParams.searchParams.append('ws_token', wsToken);
        } else {
            console.warn('WebSocket: No websocket token provided - connection may fail');
        }

        for (const key in args) {
            // Skip ws_token as we've already handled it above
            if (key !== 'ws_token') {
                urlWithParams.searchParams.append(key, args[key]);
            }
        }
        this.client = new WebSocket(urlWithParams.href);
        this.client.onopen = () => {
            console.log('WebSocket: connected');
            $('#socket-server-system-message').hide();
            if (this.onopen) {
                this.onopen();
            }
        };

        this.client.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (Object.keys(data).includes('error') && data['error'] === 'Server error') {
                this.serverError = true;
                return;
            }
            this.number++;
            if (this.onmessage) {
                try {
                    this.onmessage(data);
                }
                catch (exc) {
                    console.error(`error on message: ${exc}`);
                }
            }
        };

        this.client.onclose = (event) => {
            const sys_message = $('#socket-server-system-message');
            switch (event.code) {
                case 1000:
                    console.log('WebSocket: Closed');
                    if (this.serverError) {
                        sys_message.show();
                    }
                    break;
                default:
                    this.reconnect(page);
                    break;
            }
            // this.onclose(event);
        };

        this.client.onerror = (error) => {
            const sys_message = $('#socket-server-system-message');
            switch (error.code) {
                case 'ECONNREFUSED':
                    this.reconnect(page);
                    sys_message.show();
                    break;
                default:
                    // console.log(`WebSocket: Error - ${error.code}`);
                    // this.onerror(error);
                    break;
            }
            sys_message.show();
        };
    }

    send(data) {
        if (this.client.readyState === WebSocket.OPEN) {
            this.client.send(JSON.stringify(data));
        }
    }

    removeClientListeners() {
        this.client.onopen = null;
        this.client.onmessage = null;
        this.client.onclose = null;
        this.client.onerror = null;
    }

    reconnect(page) {
        console.log(`WebSocketClient: Retry in ${this.autoReconnectInterval}ms`);
        this.removeClientListeners();
        setTimeout(() => {
            console.log('WebSocketClient: Reconnecting...');
            this.open(page);
        }, this.autoReconnectInterval);
    }
}
