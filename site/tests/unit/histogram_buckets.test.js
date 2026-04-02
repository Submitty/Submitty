/**
 * Unit test for histogram bucket aggregation (Issue #12410).
 * Verifies that sum of bucket counts equals total students for any bucket size.
 * Run: node site/tests/unit/histogram_buckets.test.js
 */

function fillBuckets(range, increment, betterBuckets, xValue, yValue, xBuckets, max, min) {
    let loop = Math.ceil(range / increment);
    if (increment === 1) {
        loop += 1;
    }
    let tracking = min;
    for (let i = 0; i < loop; i++) {
        betterBuckets.set(tracking, 0);
        tracking = +parseFloat(tracking + increment).toPrecision(4);
        if (i === (loop - 1)) {
            if (increment === 1) {
                xBuckets.push(String(max));
            } else {
                xBuckets.push(+parseFloat(tracking - increment).toPrecision(4) + ' to <=' + max);
            }
        } else {
            xBuckets.push(+parseFloat(tracking - increment).toPrecision(4) + ' to <' + tracking);
        }
    }
    const maxMin = tracking - increment;
    for (let i = 0; i < xValue.length; i++) {
        const score = xValue[i];
        let assigned = false;
        if (score >= maxMin && score <= max) {
            betterBuckets.set(maxMin, betterBuckets.get(maxMin) + 1);
            assigned = true;
        }
        if (!assigned) {
            for (const [key, value] of betterBuckets) {
                if (key !== maxMin && score >= key && score < (key + increment)) {
                    betterBuckets.set(key, value + 1);
                    break;
                }
            }
        }
    }
    for (const [key, value] of betterBuckets) {
        yValue.push(value);
    }
}

function runTest(name, xValue, min, max, bucketSizesToTest) {
    const range = max - min;
    let passed = 0;
    let failed = 0;
    for (const increment of bucketSizesToTest) {
        const betterBuckets = new Map();
        const yValue = [];
        const xBuckets = [];
        fillBuckets(range, increment, betterBuckets, xValue, yValue, xBuckets, max, min);
        const sum = yValue.reduce((a, b) => a + b, 0);
        const expected = xValue.length;
        if (sum === expected) {
            passed++;
        } else {
            failed++;
            console.error(`  FAIL bucket size ${increment}: sum=${sum}, expected=${expected}`);
        }
    }
    if (failed > 0) {
        console.error(`FAIL ${name}: ${failed}/${bucketSizesToTest.length} bucket sizes wrong`);
        return false;
    }
    console.log(`OK ${name}: total count correct for all ${bucketSizesToTest.length} bucket sizes`);
    return true;
}

function main() {
    console.log('Testing histogram bucket aggregation (fix for #12410)...\n');

    let allPass = true;

    // Test 1: Simple range, various bucket sizes (issue scenario)
    const scores1 = [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100];
    const min1 = 10;
    const max1 = 100;
    const range1 = max1 - min1;
    const defaultBucketSize = Math.floor(range1 / 10) || range1 / 10;
    allPass &= runTest(
        'Manual/Autograding-style (scores 10-100)',
        scores1,
        min1,
        max1,
        [1, 5, 10, defaultBucketSize, 15, 20, 25, 30, 50]
    );

    // Test 2: Smaller range, bucket larger than default
    const scores2 = [0, 5, 10, 15, 20, 25, 30];
    allPass &= runTest(
        'Small range',
        scores2,
        0,
        30,
        [1, 3, 5, 10, 15]
    );

    // Test 3: Single value - in the app, min===max is handled in the template (no fillBuckets call).
    // So we only test when range > 0.

    // Test 3: Many scores, boundary-heavy (scores at bucket boundaries)
    const scores4 = [];
    for (let i = 0; i <= 100; i += 5) {
        scores4.push(i);
    }
    allPass &= runTest(
        'Scores on boundaries (0,5,...,100)',
        scores4,
        0,
        100,
        [10, 15, 20, 25, 33]
    );

    // Test 4: Float-like scores (toPrecision can create boundaries)
    const scores5 = [10.1, 20.2, 30.3, 40.4, 50.5, 60.6, 70.7, 80.8, 90.9, 100];
    allPass &= runTest(
        'Decimal scores',
        scores5,
        10.1,
        100,
        [10, 15, 20, 25]
    );

    console.log('');
    if (allPass) {
        console.log('All tests passed. Histogram bucket total is correct for all tested cases.');
        process.exit(0);
    } else {
        console.error('Some tests failed.');
        process.exit(1);
    }
}

main();
