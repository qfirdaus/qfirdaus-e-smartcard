(function (window, document) {
  'use strict';

  function toJQueryObject(input) {
    if (!window.jQuery) return null;
    if (input && input.jquery) return input;
    return window.jQuery(input);
  }

  function addStandardClasses(wrapper) {
    if (!wrapper || !wrapper.length) return;
    wrapper.addClass('dt-standard');

    var table = wrapper.find('table');
    var responsive = wrapper.closest('.table-responsive');

    if (responsive.length) {
      responsive.addClass('dt-standard');
    } else if (table.length) {
      table.wrap('<div class="table-responsive dt-standard"></div>');
    }
  }

  function decorateWrapper(tableSelector, options) {
    if (!window.jQuery) return;

    var settings = options || {};
    var table = toJQueryObject(tableSelector);
    if (!table || !table.length) return;

    var wrapper = table.closest('.dataTables_wrapper');
    if (!wrapper.length) {
      wrapper = table.parent().find('.dataTables_wrapper');
    }
    if (!wrapper.length) return;

    addStandardClasses(wrapper);

    wrapper.find('.dataTables_length select').addClass('form-select w-auto');
    wrapper.find('.dataTables_length label').addClass('mb-0');
    wrapper.find('.dataTables_filter input').addClass('form-control');

    var placeholder = settings.searchPlaceholder || '';
    if (placeholder) {
      wrapper.find('.dataTables_filter input').attr('placeholder', placeholder);
    }

    if (settings.leftInsetSelector) {
      wrapper.find(settings.leftInsetSelector).addClass('dt-standard-left-inset');
    }
  }

  function getDefaultDom() {
    return '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
      't' +
      '<"row mt-3 align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7 d-flex justify-content-md-end"p>>';
  }

  function mergeOptions(base, extra) {
    if (!window.jQuery) return Object.assign({}, base, extra || {});
    return window.jQuery.extend(true, {}, base, extra || {});
  }

  function getDefaultOptions(extra) {
    return mergeOptions({
      dom: getDefaultDom(),
      pageLength: 10,
      lengthChange: true,
      ordering: true,
      responsive: false,
      autoWidth: false
    }, extra);
  }

  window.DataTableStandard = {
    dom: getDefaultDom,
    options: getDefaultOptions,
    decorate: decorateWrapper
  };
})(window, document);
