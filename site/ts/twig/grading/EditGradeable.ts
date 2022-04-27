import { onAddComponent, importComponentsFromFile } from '../../grading/rubric-dom-callback';

$(() => {
    $(document).on('click', '#add-new-component', () => {
        onAddComponent(false);
    });

    $(document).on('click', '#add-new-peer-component', () => {
        onAddComponent(true);
    });

    $(document).on('change', '#import-components-file', () => {
        importComponentsFromFile();
    });
});
