export default {
    annotationLayerName: 'annotationLayer',
    textLayerName: 'textLayer',
    annotationSvgQuery: function () {
        return 'svg.' + this.annotationLayerName;
    },
    annotationClassQuery: function () {
        return '.' + this.annotationLayerName;
    },
    textClassQuery: function () {
        return '.' + this.textLayerName;
    }    
}
