/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./src/js/diff-prototype.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./node_modules/shave/dist/shave.es.js":
/*!*********************************************!*\
  !*** ./node_modules/shave/dist/shave.es.js ***!
  \*********************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/**\n  shave - Shave is a javascript plugin that truncates multi-line text within a html element based on set max height\n  @version v2.5.2\n  @link https://github.com/dollarshaveclub/shave#readme\n  @author Jeff Wainwright <yowainwright@gmail.com> (jeffry.in)\n  @license MIT\n**/\nfunction shave(target, maxHeight) {\n  var opts = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {};\n  if (!maxHeight) throw Error('maxHeight is required');\n  var els = typeof target === 'string' ? document.querySelectorAll(target) : target;\n  if (!els) return;\n  var character = opts.character || '…';\n  var classname = opts.classname || 'js-shave';\n  var spaces = typeof opts.spaces === 'boolean' ? opts.spaces : true;\n  var charHtml = \"<span class=\\\"js-shave-char\\\">\".concat(character, \"</span>\");\n  if (!('length' in els)) els = [els];\n\n  for (var i = 0; i < els.length; i += 1) {\n    var el = els[i];\n    var styles = el.style;\n    var span = el.querySelector(\".\".concat(classname));\n    var textProp = el.textContent === undefined ? 'innerText' : 'textContent'; // If element text has already been shaved\n\n    if (span) {\n      // Remove the ellipsis to recapture the original text\n      el.removeChild(el.querySelector('.js-shave-char'));\n      el[textProp] = el[textProp]; // eslint-disable-line\n      // nuke span, recombine text\n    }\n\n    var fullText = el[textProp];\n    var words = spaces ? fullText.split(' ') : fullText; // If 0 or 1 words, we're done\n\n    if (words.length < 2) continue; // Temporarily remove any CSS height for text height calculation\n\n    var heightStyle = styles.height;\n    styles.height = 'auto';\n    var maxHeightStyle = styles.maxHeight;\n    styles.maxHeight = 'none'; // If already short enough, we're done\n\n    if (el.offsetHeight <= maxHeight) {\n      styles.height = heightStyle;\n      styles.maxHeight = maxHeightStyle;\n      continue;\n    } // Binary search for number of words which can fit in allotted height\n\n\n    var max = words.length - 1;\n    var min = 0;\n    var pivot = void 0;\n\n    while (min < max) {\n      pivot = min + max + 1 >> 1; // eslint-disable-line no-bitwise\n\n      el[textProp] = spaces ? words.slice(0, pivot).join(' ') : words.slice(0, pivot);\n      el.insertAdjacentHTML('beforeend', charHtml);\n      if (el.offsetHeight > maxHeight) max = spaces ? pivot - 1 : pivot - 2;else min = pivot;\n    }\n\n    el[textProp] = spaces ? words.slice(0, max).join(' ') : words.slice(0, max);\n    el.insertAdjacentHTML('beforeend', charHtml);\n    var diff = spaces ? \" \".concat(words.slice(max).join(' ')) : words.slice(max);\n    el.insertAdjacentHTML('beforeend', \"<span class=\\\"\".concat(classname, \"\\\" style=\\\"display:none;\\\">\").concat(diff, \"</span>\"));\n    styles.height = heightStyle;\n    styles.maxHeight = maxHeightStyle;\n  }\n}\n\n/* harmony default export */ __webpack_exports__[\"default\"] = (shave);\n\n\n//# sourceURL=webpack:///./node_modules/shave/dist/shave.es.js?");

/***/ }),

/***/ "./src/js/diff-prototype.js":
/*!**********************************!*\
  !*** ./src/js/diff-prototype.js ***!
  \**********************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var shave__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! shave */ \"./node_modules/shave/dist/shave.es.js\");\n\nwindow.shave = shave__WEBPACK_IMPORTED_MODULE_0__[\"default\"];\n\n(function($) {\n\n    Drupal.behaviors.shave = {\n        attach: function (context, settings) {\n            $(function(){\n                $('.diff-deletedline', context).each(function() {\n                    // console.log(this);\n                    // window.shave(this, 100);\n                    // var btn = $('<button class=\"js-shave-button\">' + Drupal.t('Expand') + ' </button>',),\n                    //     textEl = this,\n                    //     textString = textEl.textContent;\n\n                    // if(!$(this).hasClass('shave-processed')) {\n                    //     $(this).append(btn);\n                    //     $(this).addClass('shave-processed');\n                    // }\n                });\n            })\n            // $('body').on('click', '.js-shave-button', function(e) {\n            //       var hasShave = this.querySelector('#demo-text .js-shave');\n            //       if (hasShave !== null) {\n            //         this.textContent = textString;\n            //         btn.textContent = 'Truncate Text ✁';\n            //         return;\n            //       }\n            //       shave(this, 120);\n            //       btn.textContent = 'Reset ⏎';\n            //       return;\n\n            // });\n        }\n    }\n\n    // Drupal.behaviors.fixedActions = {\n    //     attach: function(context, settings) {\n\n    //         $(function(){\n    //             var $items = $('.paragraphs-actions .inner', context),\n    //             inheritParentDims = function() {\n    //                 $items.each(function() {\n    //                     let $parent = $(this).parent();\n    //                     let $parentHeight = $parent.height();\n    //                     let $parentWidth = $parent.width();\n    //                     // console.log($parentHeight);\n    //                     // console.log($parentWidth);\n    //                     $(this).height($parentHeight);\n    //                     $(this).width($parentWidth);\n    //                 });\n    //             };\n\n    //             inheritParentDims();\n    //             $(window).once(\"bind-to-window\").resize(inheritParentDims);\n    //         });\n    //     }\n    // }\n\n    Drupal.behaviors.horizontalScroll = {\n        attach: function(context, settings) {\n\n            $(function(){\n                var $buttons = $('.slide-button', context);\n                $buttons.each(function() {\n                    var $container = $(this).closest('.responsive-wrapper');\n                    $(this).height($container.height());\n                    $(this).css({ top: $(this).parent().offsetTop });\n                })\n\n            });\n\n            function sideScroll(element,direction,speed,distance,step){\n                var scrollAmount = 0;\n                var slideTimer = setInterval(function(){\n                    if(direction == 'left'){\n                        element.scrollLeft -= step;\n                    } else {\n                        element.scrollLeft += step;\n                    }\n                    scrollAmount += step;\n                    if(scrollAmount >= distance){\n                        window.clearInterval(slideTimer);\n                    }\n                }, speed);\n            }\n\n            // $(context).on(\n            //     'mousedown touchstart': function () {\n            //         $(\".sidebar-menu\").animate({scrollTop: 0}, 2000);\n            //     },\n            //     'mouseup touchend': function () {\n            //         $(\".sidebar-menu\").stop(true);\n            //     }\n            // );\n\n\n            $(context).on('mousedown', '.slide-button', function() {\n\n                var container = $(this).closest('.responsive-wrapper')[0];\n                    // scrollLeft = container.scrollLeft,\n                    // scrollWidth = container.scrollWidth;\n\n                // console.log(scrollLeft, scrollWidth );\n\n                if($(this).hasClass('slide-right')) {\n                    // console.log(container);\n                    // if(scrollLeft == scrollWidth) {\n                    //   // $(this).addClass('hidden');\n                    // }\n                    sideScroll(container,'right',25,100,10);\n                }\n                if($(this).hasClass('slide-left')) {\n                    // if(scrollLeft == 0) {\n                    //   // $(this).addClass('hidden');\n                    // }\n                    sideScroll(container,'left',25,100,10);\n                }\n            });\n\n        }\n    }\n\n})(jQuery);\n\n\n//# sourceURL=webpack:///./src/js/diff-prototype.js?");

/***/ })

/******/ });