/**
 * Wrapper around the builtin WebSocket class. This provides us
 * with two major benefits:
 *  1. The websocket will attempt to reconnect if the connection closes
 *  2. It willl automatically perform JSON.parse/JSON.stringify on
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
        my_url.port = 8443;
        my_url.pathname = 'ws';
        this.url = my_url.href;
        this.heartbeat = null;
    }

    open(page) {
        console.log(`WebSocket: connecting to ${this.url}`);
        this.client = new WebSocket(this.url);

        // Clear any old heartbeats that might exist
        clearInterval(this.heartbeat);
        // Send periodic heartbeat "pings" to the server to keep the connection alive.
        this.heartbeat = setInterval((client) => {
            // Only attempt to send pings if the websocket is open and connected
            if (client.readyState == WebSocket.OPEN) {
                client.send('ping');
            }
        // The default nginx  timeout for a connection is 60 seconds. 50 should give
        // us a good buffer.
        }, 50000, this.client);


        this.client.onopen = () => {
            console.log('WebSocket: connected');
            if (this.onopen) {
                this.onopen();
            }
            const term_course_arr = document.body.dataset.courseUrl.split('/');
            const course = term_course_arr.pop();
            const term = term_course_arr.pop();
            this.client.send(JSON.stringify({'type': 'new_connection', 'page': `${term}-${course}-${page}`}));
        };

        this.client.onmessage = (event) => {
            this.number++;
            if (this.onmessage) {
                try {
                    const data = event.data;
                    // Before parsing the data, check to see if it is a heartbeat "pong"
                    if (data == 'pong') {
                        return;
                    }
                    this.onmessage(JSON.parse(data));
                }
                catch (exc) {
                    console.error(`error on message: ${exc}`);
                }
            }
        };

        this.client.onclose = (event) => {
            switch (event.code) {
                case 1000:
                    console.log('WebSocket: Closed');
                    break;
                default:
                    this.reconnect();
                    break;
            }
            //this.onclose(event);
        };

        this.client.onerror = (error) => {
            switch (error.code) {
                case 'ECONNREFUSED':
                    this.reconnect();
                    break;
                default:
                    //console.log(`WebSocket: Error - ${error.code}`);
                    //this.onerror(error);
                    break;
            }
        };
    }

    send(data) {
        this.client.send(JSON.stringify(data));
    }

    removeClientListeners() {
        this.client.onopen = null;
        this.client.onmessage = null;
        this.client.onclose = null;
        this.client.onerror = null;
    }

    reconnect() {
        console.log(`WebSocketClient: Retry in ${this.autoReconnectInterval}ms`);
        this.removeClientListeners();
        setTimeout(() => {
            console.log('WebSocketClient: Reconnecting...');
            this.open(this.url);
        }, this.autoReconnectInterval);
    }
}
