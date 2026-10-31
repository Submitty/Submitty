// Load every file under vue/src so code coverage includes the ones no other spec mounts.
// This also checks that each file compiles and loads.
const modules = import.meta.glob(
    ['../../vue/src/**/*.{vue,ts}', '!../../vue/src/**/*.{cy,spec,test}.ts'],
    { eager: true },
);

describe('Vue source files', () => {
    it('all load', () => {
        const paths = Object.keys(modules);
        expect(paths.length).to.be.greaterThan(0);
        paths.forEach((path) => {
            expect(modules[path], path).to.exist;
        });
    });
});
