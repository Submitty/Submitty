import mockPDFPage from './mockPDFPage';

export default function mockPDFDocument() {
  return {
    getPage: function (pageNumber) {
      return Promise.resolve(mockPDFPage());
    }
  };
}
