const getHeader = (headerText) => {
    return cy.contains('#data-table thead td, #data-table thead th', headerText);
};

const clickHeader = (headerText) => {
    getHeader(headerText).then(($header) => {
        const sortableHeader = $header.find('a.sortable-header');
        if (sortableHeader.length > 0) {
            cy.wrap(sortableHeader).click();
        }
        else {
            cy.wrap($header).click();
        }
    });
};

const getTableRows = () => {
    return cy.get('#data-table tbody tr').then((rows) => {
        return [...rows].map((row) => {
            return [...row.children].map((cell) => cell.textContent.trim());
        });
    });
};

const getColumnValues = (columnIndex) => {
    return getTableRows().then((rows) => rows.map((row) => row[columnIndex]));
};

const normalizeString = (value) => value.toLowerCase();

const normalizeNumber = (value) => {
    return Number.parseFloat(value) || 0;
};

const normalizeDate = (value) => {
    const time = new Date(value).getTime();
    return Number.isNaN(time) ? Number.NEGATIVE_INFINITY : time;
};

const sortValues = (values, normalize, direction) => {
    return [...values].map(normalize).sort((first, second) => {
        if (typeof first === 'string' && typeof second === 'string') {
            return direction === 'asc' ? first.localeCompare(second) : second.localeCompare(first);
        }

        return direction === 'asc' ? first - second : second - first;
    });
};

const isSorted = (values, normalize, direction) => {
    const actual = values.map(normalize);
    const expected = sortValues(values, normalize, direction);
    return Cypress._.isEqual(actual, expected);
};

const expectSorted = (values, normalize, direction) => {
    const actual = values.map(normalize);
    const expected = sortValues(values, normalize, direction);

    expect(actual).to.deep.equal(expected);
};

const getUserIds = () => {
    return getColumnValues(1);
};

const expectSameUserIds = (actualUserIds, expectedUserIds) => {
    expect([...actualUserIds].sort()).to.deep.equal([...expectedUserIds].sort());
};

const expectColumnSortsAscendingAndDescending = (headerText, columnIndex, normalize) => {
    let originalUserIds;

    getUserIds().then((userIds) => {
        originalUserIds = userIds;
    });

    clickHeader(headerText);

    getColumnValues(columnIndex).then((values) => {
        if (isSorted(values, normalize, 'desc')) {
            clickHeader(headerText);
        }
    });

    getColumnValues(columnIndex).then((values) => {
        expectSorted(values, normalize, 'asc');
    });

    getUserIds().then((userIds) => {
        expectSameUserIds(userIds, originalUserIds);
    });

    clickHeader(headerText);

    getColumnValues(columnIndex).then((values) => {
        expectSorted(values, normalize, 'desc');
    });

    getUserIds().then((userIds) => {
        expectSameUserIds(userIds, originalUserIds);
    });
};

describe('Test cases revolving around student activity dashboard sorting', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit('/courses/s26/development/activity');
        cy.get('#data-table tbody tr').should('have.length.greaterThan', 1);
    });

    it('Should sort the Registration Section column in ascending and descending order', () => {
        expectColumnSortsAscendingAndDescending('Registration Section', 0, normalizeNumber);
    });

    it('Should sort the Given Name column in ascending and descending order', () => {
        expectColumnSortsAscendingAndDescending('Given Name', 2, normalizeString);
    });

    it('Should sort the Gradeable Submission Date column in ascending and descending order', () => {
        expectColumnSortsAscendingAndDescending('Gradeable Submission Date', 4, normalizeDate);
    });
});
