export {};

declare global {
  interface Window {
    test_vite: typeof test_vite;
  }
}

function test_vite(name: string) {
  console.log(`Hello ${name}!`);
}


window.test_vite = test_vite;