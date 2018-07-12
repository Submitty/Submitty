const REGEX_HASHLESS_HEX = /^([a-f0-9]{6}|[a-f0-9]{3})$/i;

/**
 * Normalize a color value
 *
 * @param {String} color The color to normalize
 * @return {String}
 */
export default function normalizeColor(color) {
  if (REGEX_HASHLESS_HEX.test(color)) {
    color = `#${color}`;
  }
  return color;
}
