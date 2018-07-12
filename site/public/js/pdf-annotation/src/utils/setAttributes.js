const UPPER_REGEX = /[A-Z]/g;

// Don't convert these attributes from camelCase to hyphenated-attributes
const BLACKLIST = [
  'viewBox'
];

let keyCase = (key) => {
  if (BLACKLIST.indexOf(key) === -1) {
    key = key.replace(UPPER_REGEX, match => '-' + match.toLowerCase());
  }
  return key;
}

/**
 * Set attributes for a node from a map
 *
 * @param {Node} node The node to set attributes on
 * @param {Object} attributes The map of key/value pairs to use for attributes
 */
export default function setAttributes(node, attributes) {
  Object.keys(attributes).forEach((key) => {
    node.setAttribute(keyCase(key), attributes[key]);
  });
}
