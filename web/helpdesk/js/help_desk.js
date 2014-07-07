var elementId = null;
$('document').ready(function () {

    $.fn.selectRange = function(start, end) {
        if (!end) end = start;
        return this.each(function() {
            if (this.setSelectionRange) {
                this.focus();
                this.setSelectionRange(start, end);
            } else if (this.createTextRange) {
                var range = this.createTextRange();
                range.collapse(true);
                range.moveEnd('character', end);
                range.moveStart('character', start);
                range.select();
            }
        });
    };

    function split(val) {
        return val.split(/\s+/);
    }

    function getCaretPosition(ctrl) {
        var start, end;
        if (ctrl.setSelectionRange) {
            start = ctrl.selectionStart;
            end = ctrl.selectionEnd;
        } else if (document.selection && document.selection.createRange) {
            var range = document.selection.createRange();
            start = 0 - range.duplicate().moveStart('character', -100000);
            end = start + range.text.length;
        }
        return {
            start: start,
            end: end
        }
    }

    function CustomReplace(strData, strTextToReplace, strReplaceWith, replaceAt) {
        return strData.substr(0, replaceAt) + strReplaceWith + ' ' + strData.substr(replaceAt + 1 + strTextToReplace.length, strData.length - 5);
    }

    function getIndicesOf(searchStr, str, caseSensitive) {
        var startIndex = 0, searchStrLen = searchStr.length;
        var index, indices = [];
        if (!caseSensitive) {
            str = str.toLowerCase();
            searchStr = searchStr.toLowerCase();
        }
        while ((index = str.indexOf(searchStr, startIndex)) > -1) {
            indices.push(index);
            startIndex = index + searchStrLen;
        }
        return indices;
    }

    function applyGoaldAutocomplete() {

        $(".goal_autocomplete").autocomplete({

            source: function(request, response) {
                elementId = this.element[0].id;

                var textAreaElement = $(".goal_autocomplete");

                var caret = getCaretPosition(document.getElementById(elementId));

                var result = /\S+$/.exec(textAreaElement.val().slice(0, textAreaElement.val().indexOf(' ', caret.end)));
                var lastWord = result ? result[0] : null;

                $.ajax({
                    url: "/helpdesk/goal/autocomplete",
                    dataType: "json",
                    data: {
                        term: lastWord,
                        project_id: $('#project_id').val()
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },

            change: function (event, ui) {
                return false;
            },
            focus: function (event, ui) {
                return false;
            },
            select: function (event, ui) {
                var textAreaElement = $(".goal_autocomplete");

                var selectedWord = ui.item.value;
                var caret = getCaretPosition(document.getElementById(elementId));
                var result = /\S+$/.exec(textAreaElement.val().slice(0, textAreaElement.val().indexOf(' ', caret.end)));

                var lastWord = result ? result[0] : null;
                var explodeCriteria = ['=', '(', ')'];
                for (var i = 0; i < explodeCriteria.length; i++) {
                    var lastWordsParts = lastWord.split(explodeCriteria[i]);
                    lastWord = lastWordsParts[lastWordsParts.length - 1];
                }

                var indexes = getIndicesOf(lastWord, this.value, false);

                if (indexes.length) {

                    var indexToReplaceAt = 0;
                    for (var i = 0; i < indexes.length; i++ ) {
                        if (indexes[i] <= caret.start) {
                            indexToReplaceAt = indexes[i];
                        }
                    }
                    this.value = CustomReplace(this.value, lastWord, selectedWord, indexToReplaceAt);
                    $('.goal_autocomplete').selectRange(result.index + selectedWord.length + lastWord.length + 1);
                } else {
                    var terms = split(this.value);
                    // remove the current input
                    terms.pop();
                    // add the selected item
                    terms.push(ui.item.value);

                    this.value = terms.join(" ");
                }

                return false;
            }
        });
    }

    $('#btnAddGoal').on('click', function () {
        $.ajax({
            type: "POST",
            data: {
                project_id: $('#project_id').val()
            },
            url: '/helpdesk/sla/render-new-goal',
            success: function (response) {
                $('#slaGoals tbody').children().last().prev().after(response);
                applyGoaldAutocomplete();
            }
        });
    });

    applyGoaldAutocomplete();

    $(document).on('click', "[id^='delete_goal_']", function (event) {
        event.preventDefault();
        var goalId = $(this).attr("id").replace('delete_goal_', '');
        $(this).parent().parent().remove();
    })
});