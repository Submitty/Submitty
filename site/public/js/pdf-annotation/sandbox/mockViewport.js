export default function mockViewport(page) {
  return {
    width: page.offsetWidth,
    height: page.offsetHeight,
    rotation: 0,
    scale: 1
  };
}
