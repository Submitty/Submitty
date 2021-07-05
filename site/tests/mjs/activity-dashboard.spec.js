import '../../public/mjs/activity-dashboard';
import { test } from '@jest/globals';
import { comparator, helper, sortTable } from '../../public/mjs/activity-dashboard';

test('helper', () => {
    expect(helper('cat', 'apple', 0)).toEqual(false);
    expect(helper('0', '1', 0)).toEqual(true);
    expect(helper('1', '0', 0)).toEqual(false);
    expect(helper('0', '0', 0)).toEqual(false);
    expect(helper('', 'apple', 0)).toEqual(false);
    expect(helper('apple', '', 0)).toEqual(true);
    expect(helper('1', 'apple', 0)).toEqual(true);
    expect(helper('apple', '1', 0)).toEqual(false);
    expect(helper('cat', 'apple', 1)).toEqual(false);
    expect(helper('0', '1', 1)).toEqual(true);
    expect(helper('1', '0', 1)).toEqual(false);
    expect(helper('0', '0', 1)).toEqual(false);
    expect(helper('', 'apple', 1)).toEqual(true);
    expect(helper('apple', '', 1)).toEqual(false);
    expect(helper('2018-02-21 13:02:21', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('2021-02-21 13:02:21', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('2021-07-20 13:02:21', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('2021-07-21 13:02:21', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('2021-07-21 14:02:21', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('2021-07-21 14:12:21', '2021-07-21 14:12:31', 4)).toEqual(true);
    expect(helper('0', '1', 8)).toEqual(true);
    expect(helper('1', '0', 8)).toEqual(false);
    expect(helper('0', '0', 8)).toEqual(false);
    expect(helper('10', '2', 8)).toEqual(false);
    expect(helper('2', '10', 8)).toEqual(true);
});

test('comparator', () => {
    document.body.innerHTML = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left"></td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
            <tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 9:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
            <tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
            <tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;
    // row 0 will be the column names so we slice starting from 1
    const rowArray = Array.prototype.slice.call(document.getElementById('data-table').rows).slice(1);
    // testing first column
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 0, 'asc')).toEqual(true);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 0, 'asc')).toEqual(false);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 0, 'desc')).toEqual(false);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 0, 'desc')).toEqual(true);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 0, 'asc')).toEqual(true);
    expect(comparator(rowArray[2].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 0, 'asc')).toEqual(false);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 0, 'desc')).toEqual(false);
    expect(comparator(rowArray[2].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 0, 'desc')).toEqual(true);
    expect(comparator(rowArray[3].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 0, 'asc')).toEqual(true);
    expect(comparator(rowArray[2].getElementsByTagName('TD'), rowArray[3].getElementsByTagName('TD'), 0, 'asc')).toEqual(false);
    expect(comparator(rowArray[3].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 0, 'desc')).toEqual(false);
    expect(comparator(rowArray[2].getElementsByTagName('TD'), rowArray[3].getElementsByTagName('TD'), 0, 'desc')).toEqual(true);

    // testing second column
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 1, 'asc')).toEqual(true);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 1, 'asc')).toEqual(false);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 1, 'desc')).toEqual(false);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 1, 'desc')).toEqual(true);

    // testing fifth column
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 4, 'asc')).toEqual(false);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 4, 'asc')).toEqual(true);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 4, 'desc')).toEqual(true);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 4, 'desc')).toEqual(false);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 4, 'asc')).toEqual(false);
    expect(comparator(rowArray[2].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 4, 'asc')).toEqual(true);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 4, 'desc')).toEqual(true);
    expect(comparator(rowArray[2].getElementsByTagName('TD'), rowArray[0].getElementsByTagName('TD'), 4, 'desc')).toEqual(false);

    // testing nineth column
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 8, 'desc')).toEqual(false);
    expect(comparator(rowArray[0].getElementsByTagName('TD'), rowArray[1].getElementsByTagName('TD'), 8, 'asc')).toEqual(true);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 8, 'desc')).toEqual(false);
    expect(comparator(rowArray[1].getElementsByTagName('TD'), rowArray[2].getElementsByTagName('TD'), 8, 'asc')).toEqual(true);
});

test('sort', () => {
    document.body.innerHTML = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;

    const expectedSort0Asc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;
    sortTable(0,'asc');
    expect(document.body.innerHTML).toEqual(expectedSort0Asc);
    const expectedSort0Desc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;
    sortTable(0,'desc');
    expect(document.body.innerHTML).toEqual(expectedSort0Desc);

    const expectedSort1Asc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;
    sortTable(1, 'asc');
    expect(document.body.innerHTML).toEqual(expectedSort1Asc);

    const expectedSort1Desc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;

    sortTable(1, 'desc');
    expect(document.body.innerHTML).toEqual(expectedSort1Desc);

    const expectedSort4Asc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;

    sortTable(4, 'asc');
    expect(document.body.innerHTML).toEqual(expectedSort4Asc);

    const expectedSort4Desc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;

    sortTable(4, 'desc');
    expect(document.body.innerHTML).toEqual(expectedSort4Desc);

    const expectedSort8Asc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;

    sortTable(8, 'asc');
    expect(document.body.innerHTML).toEqual(expectedSort8Asc);

    const expectedSort8Desc = `<table id="data-table" class="table table-striped mobile-table directory-table sortable">
        <thead>
            <tr>
                <td id="0" style="cursor: pointer">Registration Section <i class="fas fa-angle-down"></i></td>
                <td id="1" style="cursor: pointer">User ID <i class="fas"></i></td>
                <td id="2" style="cursor: pointer">First Name <i class="fas"></i></td>
                <td id="3" style="cursor: pointer">Last Name <i class="fas"></i></td>
                <td id="4" style="cursor: pointer">Gradeable Submission Date <i class="fas"></i></td>
                <td id="5" style="cursor: pointer">Forum View Date <i class="fas"></i></td>
                <td id="6" style="cursor: pointer">Forum Post Date <i class="fas"></i></td>
                <td id="7" style="cursor: pointer">Gradeable Access Date <i class="fas"></i></td>
                <td id="8" style="cursor: pointer">Number of Poll Responses <i class="fas"></i></td>
                <td id="9" style="cursor: pointer">Office Hours Queue Date <i class="fas"></i></td>
                <td id="10" style="cursor: pointer; display:none">Flagged <i class="fas"></i></td>
            </tr>
        </thead>

        <tbody id="tbody">
            <tr id="bechta">
                <td>Audit</td>
                <td class="align-left">bechta</td>
                <td class="align-left">Abigale</td>
                <td class="align-left">Bechtelar</td>
                <td>1971-12-30 23:59:59</td>
                <td></td>
                <td></td>
                <td></td>
                <td>21</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="zoezoe">
                <td></td>
                <td class="align-left">zoezoe</td>
                <td class="align-left">Zoe</td>
                <td class="align-left">Zoe</td>
                <td></td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>10</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="batdiddle">
                <td>4</td>
                <td class="align-left">batdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Batdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="bitdiddle">
                <td>3</td>
                <td class="align-left">bitdiddle</td>
                <td class="align-left">Ben</td>
                <td class="align-left">Bitdiddle</td>
                <td>2021-07-01 11:35:21</td>
                <td></td>
                <td>2018-04-03 18:47:02</td>
                <td></td>
                <td>9</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="aphacker">
                <td>1</td>
                <td class="align-left">aphacker</td>
                <td class="align-left">Alyssa P</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr><tr id="azhacker">
                <td>1</td>
                <td class="align-left">azhacker</td>
                <td class="align-left">Alyssa Z</td>
                <td class="align-left">Hacker</td>
                <td>2021-07-01 10:21:31</td>
                <td></td>
                <td>2018-05-23 11:55:27</td>
                <td></td>
                <td>0</td>
                <td></td>
                <td style="display: none;">False</td>
            </tr>
        </tbody>
    </table>`;
    sortTable(8, 'desc');
    expect(document.body.innerHTML).toEqual(expectedSort8Desc);
});
