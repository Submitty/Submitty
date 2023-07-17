 /**
 * ---------------------
 *
 * pubsub.twig
 *
 * A simple implementation of publisher-subcriber for the coupling
 * between grading panels.
 * 
 * Inspired by https://dev.to/anishkumar/design-patterns-in-javascript-publish-subscribe-or-pubsub-20gf.
 *
 * ---------------------
 */

export default class PubSub {

    // -------------------------------

    // Constructor/Variables:

    /**
     * Creates a PubSub announcer object.
     */
    constructor() {
        this.nameToSubscribers = new Map();
    }


    // -------------------------------

    // Methods:

    /**
     * Add a function to call when a certain signal is published.
     *
     * @param {string} Name of signal to respond to.
     * @param {function} Function to call when signal is published.
     */
    subscribe(signal_name, subscribe_function) {
        this.nameToSubscribers.set(signal_name, subscribe_function);
    }


    /**
     * Publish a signal and call all subscribers for it.
     * 
     * @param {string} Name of signal to publish.
     * @param {Object} Array of arguments to pass to subscribers for this signal.
     */    
    publish(signal_name, arguments) {
        this.subscribers[signal_name].forEach(subscribe_function => subscribe_function(...arguments));
    }


    // -------------------------------
}