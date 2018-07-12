const REGEXP = /[xy]/g
const PATTERN = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';

function replacement(c) {
  let r = Math.random()*16|0;
  let v = c == 'x' ? r : (r&0x3|0x8);
  return v.toString(16);
}

/**
 * Generate a univierally unique identifier
 *
 * @return {String}
 */
export default function uuid() {
  return PATTERN.replace(REGEXP, replacement);
}
