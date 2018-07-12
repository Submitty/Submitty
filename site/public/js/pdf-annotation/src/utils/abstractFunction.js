/**
 * Throw an Error for an abstract function that hasn't been implemented.
 *
 * @param {String} name The name of the abstract function
 */
export default function abstractFunction(name) {
  throw new Error(name + ' is not implemented');
}
