'use strict';

import 'bootstrap';

let Masonry = require('masonry-layout'),
    $ = require('jquery'),
    imagesLoaded = require('imagesloaded'),
    allPhotosContainerSelector = '.js-allphotos-container',
    deferredMaster = $.Deferred(),
    deferredNext = deferredMaster,
    loadedDates = [],
    queuedDates = [];

function initMasonry(selector)
{
    if ($(selector).length) {
        return new Masonry(selector, {
            itemSelector: '.grid-item',
            columnWidth: '.grid-sizer',
            percentPosition: true
        });
    }
    return null;
}

function replaceAll(str, mapObj)
{
    let re = new RegExp(Object.keys(mapObj).join("|"), "gi");

    return str.replace(re, (matched) => {
        return mapObj[matched];
    });
}

let LoadingProgress = {
    $element: null,

    show: function (message) {
        if (message === undefined) {
            message = '';
        }

        if (this.$element === null) {
            this.$element = $('.js-loading-progress');
        }
        this.$element.find('.card-body').html(message);
        this.$element.show();
    },

    hide: function() {
        if (this.$element === null) {
            this.$element = $('.js-loading-progress');
        }

        this.$element.hide();
        this.$element.find('.card-body').html('');
    }
};

function renderDate (date)
{
    let photoHtml = '';
    for (let i in loadedDates[date]) {
        let photo = loadedDates[date][i];
        photoHtml += replaceAll(
            $('.js-template-grid-item').html(), {
                '%link%': photo.link,
                '%title%': photo.title,
                '%image%': photo.image
            }
        );
    }

    $(allPhotosContainerSelector).append(replaceAll(
        $('.js-template-year').html(), {
            '%date%': date,
            '%photoHtml%': photoHtml
        }
    ));

    let selector = '.grid[data-date="' + date + '"]',
        mason = initMasonry(selector);
    imagesLoaded(selector).on('progress', () => {
        mason.layout();
    });
}

function submitDate($form)
{
    let $submit = $form.find('button[type=submit]'),
        submitLabel = $submit.html();

    $submit.html('...');
    $submit.prop('disabled', true);

    LoadingProgress.show('Loading album');

    $.post($form.data('action'), $form.serialize(), 'json')
        .done((response) => {
            if (response.error) {
                alert(response.message);
                return;
            }
            if (!response.list) {
                alert('Something went wrong');
                return;
            }

            $(allPhotosContainerSelector).html('');

            deferredMaster.resolve();
            for (let i in response.list) {
                queuedDates.push(response.list[i]);
                deferredNext = deferredNext.pipe(function () {
                    let date = queuedDates.shift();
                    LoadingProgress.show('Loading ' + date);

                    return $.post(
                        $form.data('photos-action'),
                        {'date': date, 'mainLink': response.mainLink},
                        'json'
                    ).done((response) => {
                        if (response.error) {
                            console.log('Request for ' + date + ' done with error');
                            return;
                        }

                        if (response.list && response.list.length) {
                            loadedDates[date] = [];
                            for (let j in response.list) {
                                loadedDates[date].push(response.list[j]);
                            }
                            renderDate(date);
                        }
                    }).fail(() => {
                        console.log('Request for ' + date + ' failed');
                        // render fail?
                    }).always(() => {
                        LoadingProgress.hide();
                    });
                });
            }
        }).fail(() => {
            alert('Something went wrong');
        }).always(() => {
            $submit.prop('disabled', false);
            $submit.html(submitLabel);
            LoadingProgress.hide();
        });
}

$(() => {
    initMasonry('.grid');

    $('.js-year').change(function () {
        let $form = $(this).closest('form');
        $form.find('input[name=js]').val(1);

        submitDate($form);
    });

    $('.js-date-form').submit(function (e) {
        e.preventDefault();

        let $form = $(this);

        submitDate($form);
    });
});
