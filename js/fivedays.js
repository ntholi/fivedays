// FiveDays Rubric grading UI module for Moodle

M.gradingform_fivedays = M.gradingform_fivedays || {};

M.gradingform_fivedays.init = function(Y, options) {
    var self = this;
    self.Y = Y;
    self.options = options || {};

    Y.all('.fivedays-criterion').each(function(criterion) {
        self.initCriterion(criterion);
    });
};

M.gradingform_fivedays.initCriterion = function(criterion) {
    var self = this;
    var Y = self.Y;

    criterion.all('.fivedays-level').each(function(level) {
        level.on('click', function(e) {
            if (criterion.hasClass('locked')) {
                return;
            }
            
            var radio = level.one('input[type="radio"]');
            if (radio) {
                self.selectLevel(criterion, level, radio);
            }
        });
    });

    var customScoreInput = criterion.one('.fivedays-custom-score input');
    if (customScoreInput) {
        customScoreInput.on('change', function(e) {
            self.updateCustomScore(criterion, customScoreInput);
        });
        customScoreInput.on('keyup', function(e) {
            self.validateCustomScore(criterion, customScoreInput);
        });
    }
};

M.gradingform_fivedays.selectLevel = function(criterion, level, radio) {
    var self = this;
    var Y = self.Y;

    criterion.all('.fivedays-level').removeClass('selected checked');
    level.addClass('selected checked');
    radio.set('checked', true);

    var scoreData = level.getData('score');
    var rangeMin = level.getData('rangemin');
    var rangeMax = level.getData('rangemax');

    var customInput = criterion.one('.fivedays-custom-score input');
    if (customInput) {
        customInput.set('value', scoreData);
        customInput.set('min', rangeMin);
        customInput.set('max', rangeMax);
        
        var rangeLabel = criterion.one('.fivedays-range-label');
        if (rangeLabel) {
            rangeLabel.setHTML(M.util.get_string('scorerange', 'gradingform_fivedays', {min: rangeMin, max: rangeMax}));
        }
    }
};

M.gradingform_fivedays.updateCustomScore = function(criterion, input) {
    var self = this;
    var value = parseFloat(input.get('value'));
    var min = parseFloat(input.get('min'));
    var max = parseFloat(input.get('max'));

    if (isNaN(value)) {
        return;
    }

    if (value < min) {
        input.set('value', min);
    } else if (value > max) {
        input.set('value', max);
    }
};

M.gradingform_fivedays.validateCustomScore = function(criterion, input) {
    var value = input.get('value');
    
    if (value === '' || value === '-') {
        input.removeClass('error');
        return true;
    }

    var numValue = parseFloat(value);
    if (isNaN(numValue)) {
        input.addClass('error');
        return false;
    }

    input.removeClass('error');
    return true;
};

M.gradingform_fivedays.showRangeInfo = function(level) {
    var rangeMin = level.getData('rangemin');
    var rangeMax = level.getData('rangemax');
    var score = level.getData('score');
    
    var tooltip = level.one('.fivedays-range-tooltip');
    if (tooltip) {
        tooltip.setStyle('display', 'block');
    }
};

M.gradingform_fivedays.hideRangeInfo = function(level) {
    var tooltip = level.one('.fivedays-range-tooltip');
    if (tooltip) {
        tooltip.setStyle('display', 'none');
    }
};
