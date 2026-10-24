// FiveDays Rubric grading UI module for Moodle

M.gradingform_fivedays = M.gradingform_fivedays || {};

M.gradingform_fivedays.init = function(Y, options) {
    var self = this;
    self.Y = Y;
    self.options = options || {};

    Y.all('tr.criterion').each(function(criterion) {
        if (criterion.getData('fivedaysinit')) {
            return;
        }
        criterion.setData('fivedaysinit', true);
        self.initCriterion(criterion);
    });
};

M.gradingform_fivedays.initCriterion = function(criterion) {
    var self = this;

    criterion.all('.level').each(function(level) {
        var radio = level.one('input[type="radio"]');
        if (!radio) {
            return;
        }
        level.on('click', function(e) {
            if (criterion.hasClass('locked')) {
                return;
            }
            self.selectLevel(criterion, level, radio);
        });
    });

    var customScoreInput = criterion.one('td.customscore input');
    if (customScoreInput) {
        customScoreInput.on('change', function() {
            self.clampCustomScore(customScoreInput);
        });
    }
};

M.gradingform_fivedays.selectLevel = function(criterion, level, radio) {
    criterion.all('.level').removeClass('selected checked');
    level.addClass('selected checked');
    radio.set('checked', true);

    var customInput = criterion.one('td.customscore input');
    if (customInput) {
        customInput.set('value', radio.getAttribute('data-score'));
    }
};

M.gradingform_fivedays.clampCustomScore = function(input) {
    var value = parseFloat(input.get('value'));
    if (isNaN(value)) {
        return;
    }
    var min = parseFloat(input.getAttribute('min'));
    var max = parseFloat(input.getAttribute('max'));
    if (!isNaN(min) && value < min) {
        input.set('value', min);
    } else if (!isNaN(max) && value > max) {
        input.set('value', max);
    }
};
