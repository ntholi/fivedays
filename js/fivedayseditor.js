// FiveDays Rubric editor module for Moodle

M.gradingform_fivedayseditor = M.gradingform_fivedayseditor || {};

M.gradingform_fivedayseditor.init = function(Y, options) {
    var self = this;
    self.Y = Y;
    self.options = options || {};
    self.name = options.name;
    self.criterionCounter = 0;
    self.levelCounter = 0;

    Y.all('.fivedays-criteria').each(function(table) {
        self.initTable(table);
    });
    
    self.initAddCriterionButton();
};

M.gradingform_fivedayseditor.initTable = function(table) {
    var self = this;
    var Y = self.Y;

    table.all('.criterion').each(function(criterion, index) {
        self.initCriterion(criterion);
        var criterionId = criterion.getData('id');
        if (criterionId && parseInt(criterionId) > self.criterionCounter) {
            self.criterionCounter = parseInt(criterionId);
        }
    });

    table.all('.level').each(function(level) {
        var levelId = level.getData('id');
        if (levelId && parseInt(levelId) > self.levelCounter) {
            self.levelCounter = parseInt(levelId);
        }
    });
};

M.gradingform_fivedayseditor.initCriterion = function(criterion) {
    var self = this;
    var Y = self.Y;

    var moveUp = criterion.one('.moveup');
    if (moveUp) {
        moveUp.on('click', function(e) {
            e.preventDefault();
            self.moveCriterion(criterion, -1);
        });
    }

    var moveDown = criterion.one('.movedown');
    if (moveDown) {
        moveDown.on('click', function(e) {
            e.preventDefault();
            self.moveCriterion(criterion, 1);
        });
    }

    var deleteBtn = criterion.one('.delete');
    if (deleteBtn) {
        deleteBtn.on('click', function(e) {
            e.preventDefault();
            self.deleteCriterion(criterion);
        });
    }

    var addLevelBtn = criterion.one('.addlevel');
    if (addLevelBtn) {
        addLevelBtn.on('click', function(e) {
            e.preventDefault();
            self.addLevel(criterion);
        });
    }

    criterion.all('.level').each(function(level) {
        self.initLevel(level, criterion);
    });

    var descriptionField = criterion.one('.description textarea, .description input');
    if (descriptionField) {
        self.initEditableField(descriptionField);
    }
};

M.gradingform_fivedayseditor.initLevel = function(level, criterion) {
    var self = this;
    var Y = self.Y;

    var deleteBtn = level.one('.delete');
    if (deleteBtn) {
        deleteBtn.on('click', function(e) {
            e.preventDefault();
            self.deleteLevel(level, criterion);
        });
    }

    var scoreField = level.one('.score input');
    if (scoreField) {
        self.initEditableField(scoreField);
        scoreField.on('change', function() {
            self.recalculateRanges(criterion);
        });
    }

    var definitionField = level.one('.definition textarea, .definition input');
    if (definitionField) {
        self.initEditableField(definitionField);
    }
};

M.gradingform_fivedayseditor.initEditableField = function(field) {
    field.on('focus', function() {
        field.addClass('editing');
    });
    field.on('blur', function() {
        field.removeClass('editing');
    });
};

M.gradingform_fivedayseditor.moveCriterion = function(criterion, direction) {
    var self = this;
    var Y = self.Y;
    var parent = criterion.get('parentNode');
    var siblings = parent.all('.criterion');
    var index = siblings.indexOf(criterion);
    var newIndex = index + direction;

    if (newIndex < 0 || newIndex >= siblings.size()) {
        return;
    }

    var targetCriterion = siblings.item(newIndex);
    if (direction < 0) {
        parent.insertBefore(criterion, targetCriterion);
    } else {
        parent.insertBefore(criterion, targetCriterion.next());
    }

    self.updateSortOrder(parent);
};

M.gradingform_fivedayseditor.updateSortOrder = function(container) {
    var self = this;
    container.all('.criterion').each(function(criterion, index) {
        var sortOrderInput = criterion.one('input[name$="[sortorder]"]');
        if (sortOrderInput) {
            sortOrderInput.set('value', index + 1);
        }
    });
};

M.gradingform_fivedayseditor.deleteCriterion = function(criterion) {
    var self = this;
    var Y = self.Y;

    if (!confirm(M.util.get_string('confirmdeletecriterion', 'gradingform_fivedays'))) {
        return;
    }

    var parent = criterion.get('parentNode');
    var deleteInput = criterion.one('input[name$="[delete]"]');
    if (deleteInput) {
        deleteInput.set('value', '1');
        criterion.setStyle('display', 'none');
    } else {
        criterion.remove();
    }

    self.updateSortOrder(parent);
};

M.gradingform_fivedayseditor.addLevel = function(criterion) {
    var self = this;
    var Y = self.Y;

    self.levelCounter++;
    var newLevelId = 'NEWID' + self.levelCounter;
    var criterionId = criterion.getData('id');
    var levelsContainer = criterion.one('.levels');

    var existingLevels = criterion.all('.level');
    var maxScore = 0;
    existingLevels.each(function(level) {
        var scoreInput = level.one('.score input');
        if (scoreInput) {
            var score = parseFloat(scoreInput.get('value')) || 0;
            if (score > maxScore) {
                maxScore = score;
            }
        }
    });
    var newScore = maxScore + 10;

    var baseName = self.name + '[criteria][' + criterionId + '][levels][' + newLevelId + ']';

    var levelHtml = '<div class="level" data-id="' + newLevelId + '">' +
        '<div class="level-inner">' +
        '<div class="definition">' +
        '<textarea name="' + baseName + '[definition]" rows="2" cols="20"></textarea>' +
        '</div>' +
        '<div class="score">' +
        '<input type="text" name="' + baseName + '[score]" value="' + newScore + '" size="3" />' +
        ' ' + M.util.get_string('points', 'gradingform_fivedays') +
        '</div>' +
        '<div class="level-actions">' +
        '<a href="#" class="delete" title="' + M.util.get_string('leveldelete', 'gradingform_fivedays', newLevelId) + '">' +
        '<i class="icon fa fa-trash"></i></a>' +
        '</div>' +
        '</div>' +
        '</div>';

    if (levelsContainer) {
        var addLevelBtn = levelsContainer.one('.addlevel');
        if (addLevelBtn) {
            addLevelBtn.insert(levelHtml, 'before');
        } else {
            levelsContainer.append(levelHtml);
        }
    }

    var newLevel = criterion.one('.level[data-id="' + newLevelId + '"]');
    if (newLevel) {
        self.initLevel(newLevel, criterion);
    }

    self.recalculateRanges(criterion);
};

M.gradingform_fivedayseditor.deleteLevel = function(level, criterion) {
    var self = this;
    var Y = self.Y;

    var levelCount = criterion.all('.level:not([style*="display: none"])').size();
    if (levelCount <= 2) {
        alert(M.util.get_string('err_mintwolevels', 'gradingform_fivedays'));
        return;
    }

    if (!confirm(M.util.get_string('confirmdeletelevel', 'gradingform_fivedays'))) {
        return;
    }

    var deleteInput = level.one('input[name$="[delete]"]');
    if (deleteInput) {
        deleteInput.set('value', '1');
        level.setStyle('display', 'none');
    } else {
        level.remove();
    }

    self.recalculateRanges(criterion);
};

M.gradingform_fivedayseditor.recalculateRanges = function(criterion) {
    var self = this;
    var levels = [];

    criterion.all('.level:not([style*="display: none"])').each(function(level) {
        var scoreInput = level.one('.score input');
        if (scoreInput) {
            levels.push({
                node: level,
                score: parseFloat(scoreInput.get('value')) || 0
            });
        }
    });

    levels.sort(function(a, b) {
        return a.score - b.score;
    });

    for (var i = 0; i < levels.length; i++) {
        var rangeMin, rangeMax;

        if (i === 0) {
            rangeMin = 0;
        } else {
            rangeMin = Math.round((levels[i - 1].score + levels[i].score) / 2);
        }

        if (i === levels.length - 1) {
            rangeMax = levels[i].score;
        } else {
            rangeMax = Math.round((levels[i].score + levels[i + 1].score) / 2);
        }

        var rangeDisplay = levels[i].node.one('.range-display');
        if (rangeDisplay) {
            rangeDisplay.setHTML('(' + rangeMin + ' - ' + rangeMax + ')');
        }

        levels[i].node.setData('rangemin', rangeMin);
        levels[i].node.setData('rangemax', rangeMax);
    }
};

M.gradingform_fivedayseditor.initAddCriterionButton = function() {
    var self = this;
    var Y = self.Y;

    Y.all('.addcriterion').each(function(button) {
        button.on('click', function(e) {
            e.preventDefault();
            self.addCriterion(button);
        });
    });
};

M.gradingform_fivedayseditor.addCriterion = function(button) {
    var self = this;
    var Y = self.Y;

    self.criterionCounter++;
    var newCriterionId = 'NEWID' + self.criterionCounter;
    
    self.levelCounter++;
    var level1Id = 'NEWID' + self.levelCounter;
    self.levelCounter++;
    var level2Id = 'NEWID' + self.levelCounter;

    var criteriaContainer = button.ancestor('.fivedays-criteria') || button.get('parentNode');
    var baseName = self.name + '[criteria][' + newCriterionId + ']';

    var criterionHtml = '<div class="criterion" data-id="' + newCriterionId + '">' +
        '<div class="criterion-header">' +
        '<div class="description">' +
        '<textarea name="' + baseName + '[description]" rows="3" cols="40" placeholder="' + 
        M.util.get_string('criterionempty', 'gradingform_fivedays') + '"></textarea>' +
        '</div>' +
        '<div class="criterion-actions">' +
        '<input type="hidden" name="' + baseName + '[sortorder]" value="999" />' +
        '<a href="#" class="moveup" title="' + M.util.get_string('criterionmoveup', 'gradingform_fivedays') + '">' +
        '<i class="icon fa fa-arrow-up"></i></a>' +
        '<a href="#" class="movedown" title="' + M.util.get_string('criterionmovedown', 'gradingform_fivedays') + '">' +
        '<i class="icon fa fa-arrow-down"></i></a>' +
        '<a href="#" class="delete" title="' + M.util.get_string('criteriondelete', 'gradingform_fivedays') + '">' +
        '<i class="icon fa fa-trash"></i></a>' +
        '</div>' +
        '</div>' +
        '<div class="levels">' +
        self.createLevelHtml(baseName, level1Id, 0) +
        self.createLevelHtml(baseName, level2Id, 10) +
        '<a href="#" class="addlevel btn btn-secondary btn-sm">' + 
        M.util.get_string('addlevel', 'gradingform_fivedays') + '</a>' +
        '</div>' +
        '</div>';

    button.insert(criterionHtml, 'before');

    var newCriterion = Y.one('.criterion[data-id="' + newCriterionId + '"]');
    if (newCriterion) {
        self.initCriterion(newCriterion);
        var parent = newCriterion.get('parentNode');
        self.updateSortOrder(parent);
    }
};

M.gradingform_fivedayseditor.createLevelHtml = function(baseName, levelId, score) {
    var self = this;
    var levelBaseName = baseName.replace(/\[criteria\]\[[^\]]+\]/, '[criteria][$1][levels][' + levelId + ']');
    levelBaseName = baseName + '[levels][' + levelId + ']';

    return '<div class="level" data-id="' + levelId + '">' +
        '<div class="level-inner">' +
        '<div class="definition">' +
        '<textarea name="' + levelBaseName + '[definition]" rows="2" cols="20" placeholder="' + 
        M.util.get_string('levelempty', 'gradingform_fivedays') + '"></textarea>' +
        '</div>' +
        '<div class="score">' +
        '<input type="text" name="' + levelBaseName + '[score]" value="' + score + '" size="3" />' +
        ' ' + M.util.get_string('points', 'gradingform_fivedays') +
        '<span class="range-display"></span>' +
        '</div>' +
        '<div class="level-actions">' +
        '<a href="#" class="delete" title="' + M.util.get_string('leveldelete', 'gradingform_fivedays', levelId) + '">' +
        '<i class="icon fa fa-trash"></i></a>' +
        '</div>' +
        '</div>' +
        '</div>';
};
