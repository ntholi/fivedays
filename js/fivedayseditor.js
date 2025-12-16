// FiveDays Rubric editor module for Moodle

M.gradingform_fivedayseditor = M.gradingform_fivedayseditor || {};

M.gradingform_fivedayseditor.init = function(Y, options) {
    var self = this;
    self.Y = Y;
    self.options = options || {};
    self.name = options.name;
    self.criterionCounter = 0;
    self.levelCounter = 0;

    // Find the criteria table by ID or class
    var criteriaTable = Y.one('#' + self.name + '-criteria');
    if (criteriaTable) {
        self.initTable(criteriaTable);
    }

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

    // Look for criterion action buttons in the controls/criterion-actions container
    var criterionActions = criterion.one('.criterion-actions');
    if (criterionActions) {
        var moveUp = criterionActions.one('.moveup');
        if (moveUp) {
            moveUp.on('click', function(e) {
                e.preventDefault();
                self.moveCriterion(criterion, -1);
            });
        }

        var moveDown = criterionActions.one('.movedown');
        if (moveDown) {
            moveDown.on('click', function(e) {
                e.preventDefault();
                self.moveCriterion(criterion, 1);
            });
        }

        var deleteBtn = criterionActions.one('.delete');
        if (deleteBtn) {
            deleteBtn.on('click', function(e) {
                e.preventDefault();
                self.deleteCriterion(criterion);
            });
        }
    }

    // Find the add level button (could be an input or anchor)
    var addLevelBtn = criterion.one('td.addlevel input, td.addlevel .addlevel, .addlevel');
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

    // Find delete button in level-actions container
    var levelActions = level.one('.level-actions');
    if (levelActions) {
        var deleteBtn = levelActions.one('.delete');
        if (deleteBtn) {
            deleteBtn.on('click', function(e) {
                e.preventDefault();
                self.deleteLevel(level, criterion);
            });
        }
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
    var levelsContainer = criterion.one('.levels-container');

    var existingLevels = criterion.all('.level');
    var maxScore = 0;
    var levelCount = 0;
    existingLevels.each(function(level) {
        levelCount++;
        var scoreInput = level.one('.score input');
        if (scoreInput) {
            var score = parseFloat(scoreInput.get('value')) || 0;
            if (score > maxScore) {
                maxScore = score;
            }
        }
    });
    var newScore = maxScore + 10;
    var newIndex = levelCount + 1;

    var baseName = self.name + '[criteria][' + criterionId + ']';
    var levelBaseName = baseName + '[levels][' + newLevelId + ']';

    var levelHtml = '<div class="level" id="' + self.name + '-criteria-' + criterionId + '-levels-' + newLevelId + '" data-id="' + newLevelId + '">' +
        '<div class="definition d-block input-group">' +
        '<textarea name="' + levelBaseName + '[definition]" cols="10" rows="4" class="form-control" ' +
        'aria-label="' + M.util.get_string('levelempty', 'gradingform_fivedays') + '"></textarea>' +
        '</div>' +
        '<div class="score d-inline">' +
        '<label for="' + self.name + '-criteria-' + criterionId + '-levels-' + newLevelId + '-score" class="sr-only"></label>' +
        '<input type="number" name="' + levelBaseName + '[score]" ' +
        'id="' + self.name + '-criteria-' + criterionId + '-levels-' + newLevelId + '-score" ' +
        'value="' + newScore + '" class="form-control form-control-sm" step="any" min="0" />' +
        '<span>' + M.util.get_string('points', 'gradingform_fivedays') + '</span>' +
        '</div>' +
        '<div class="level-actions mt-2">' +
        '<a href="#" class="delete" title="' + M.util.get_string('leveldelete', 'gradingform_fivedays', newIndex) + '">' +
        '<i class="icon fa fa-trash"></i></a>' +
        '</div>' +
        '</div>';

    if (levelsContainer) {
        levelsContainer.append(levelHtml);
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

    // Find the add criterion button by ID
    var addBtn = Y.one('#' + self.name + '-criteria-addcriterion');
    if (addBtn) {
        addBtn.on('click', function(e) {
            e.preventDefault();
            self.addCriterion(addBtn);
        });
    }

    // Also handle any buttons with .addcriterion class
    Y.all('.addcriterion').each(function(button) {
        if (button !== addBtn) {
            button.on('click', function(e) {
                e.preventDefault();
                self.addCriterion(button);
            });
        }
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

    var criteriaTable = Y.one('#' + self.name + '-criteria');
    var baseName = self.name + '[criteria][' + newCriterionId + ']';

    // Create a table row matching PHP renderer structure
    var criterionHtml = '<tr class="criterion" id="' + self.name + '-criteria-' + newCriterionId + '" data-id="' + newCriterionId + '">' +
        '<td class="controls">' +
        '<div class="criterion-actions">' +
        '<a href="#" class="moveup" title="' + M.util.get_string('criterionmoveup', 'gradingform_fivedays') + '">' +
        '<i class="icon fa fa-arrow-up"></i></a>' +
        '<a href="#" class="delete" title="' + M.util.get_string('criteriondelete', 'gradingform_fivedays') + '">' +
        '<i class="icon fa fa-trash"></i></a>' +
        '<a href="#" class="movedown" title="' + M.util.get_string('criterionmovedown', 'gradingform_fivedays') + '">' +
        '<i class="icon fa fa-arrow-down"></i></a>' +
        '</div>' +
        '</td>' +
        '<input type="hidden" name="' + baseName + '[sortorder]" value="999" />' +
        '<td class="description" id="' + self.name + '-criteria-' + newCriterionId + '-description-cell">' +
        '<textarea name="' + baseName + '[description]" id="' + self.name + '-criteria-' + newCriterionId + '-description" ' +
        'cols="10" rows="5" class="form-control" aria-label="' + M.util.get_string('criterionempty', 'gradingform_fivedays') + '" ' +
        'placeholder="' + M.util.get_string('criterionempty', 'gradingform_fivedays') + '"></textarea>' +
        '</td>' +
        '<td class="levels">' +
        '<div class="levels-container d-flex flex-wrap">' +
        self.createLevelHtml(baseName, level1Id, 0, 1) +
        self.createLevelHtml(baseName, level2Id, 10, 2) +
        '</div>' +
        '</td>' +
        '<td class="addlevel">' +
        '<input type="submit" name="' + baseName + '[levels][addlevel]" id="' + self.name + '-criteria-' + newCriterionId + '-levels-addlevel" ' +
        'value="' + M.util.get_string('addlevel', 'gradingform_fivedays') + '" class="addlevel btn btn-secondary" />' +
        '</td>' +
        '</tr>';

    if (criteriaTable) {
        criteriaTable.append(criterionHtml);
    }

    var newCriterion = Y.one('#' + self.name + '-criteria-' + newCriterionId);
    if (newCriterion) {
        self.initCriterion(newCriterion);
        self.updateSortOrder(criteriaTable);
    }
};

M.gradingform_fivedayseditor.createLevelHtml = function(baseName, levelId, score, index) {
    var self = this;
    var levelBaseName = baseName + '[levels][' + levelId + ']';
    var indexStr = index || levelId;

    return '<div class="level" id="' + baseName.replace(/\[/g, '-').replace(/\]/g, '') + '-levels-' + levelId + '" data-id="' + levelId + '">' +
        '<div class="definition d-block input-group">' +
        '<textarea name="' + levelBaseName + '[definition]" cols="10" rows="4" class="form-control" ' +
        'aria-label="' + M.util.get_string('levelempty', 'gradingform_fivedays') + '" ' +
        'placeholder="' + M.util.get_string('levelempty', 'gradingform_fivedays') + '"></textarea>' +
        '</div>' +
        '<div class="score d-inline">' +
        '<label for="' + baseName.replace(/\[/g, '-').replace(/\]/g, '') + '-levels-' + levelId + '-score" class="sr-only"></label>' +
        '<input type="number" name="' + levelBaseName + '[score]" ' +
        'id="' + baseName.replace(/\[/g, '-').replace(/\]/g, '') + '-levels-' + levelId + '-score" ' +
        'value="' + score + '" class="form-control form-control-sm" step="any" min="0" />' +
        '<span>' + M.util.get_string('points', 'gradingform_fivedays') + '</span>' +
        '</div>' +
        '<div class="level-actions mt-2">' +
        '<a href="#" class="delete" title="' + M.util.get_string('leveldelete', 'gradingform_fivedays', indexStr) + '">' +
        '<i class="icon fa fa-trash"></i></a>' +
        '</div>' +
        '</div>';
};
