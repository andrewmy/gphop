import 'bootstrap';

var Masonry = require('masonry-layout');

var masonGrid = new Masonry('.grid', {
    itemSelector: '.grid-item', // use a separate class for itemSelector, other than .col-
    columnWidth: '.grid-sizer',
    percentPosition: true
});
