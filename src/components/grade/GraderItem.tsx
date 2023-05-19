import { Slider, Text, Stack, Divider, Textarea, Flex } from '@mantine/core';
import React from 'react';
import { Grade } from './GraderPanel';

export default function GraderItem({
  rubric,
  grades,
  setGrades,
  setTotalPoints,
}: {
  rubric: Rubric;
  grades: Grade[];
  setGrades: (value: Grade[] | ((prevVar: Grade[]) => Grade[])) => void;
  setTotalPoints: (value: number | ((prevVar: number) => number)) => void;
}) {
  const grade = grades.find((it) => it.title == rubric.title);
  const [value, setValue] = React.useState(grade?.points || 0);
  const [comment, setComment] = React.useState(grade?.comment || '');

  React.useEffect(() => {
    setValue(grade?.points || 0);
    setComment(grade?.comment || '');
    const totalPoints = grades.reduce((sum, grade) => sum + grade.points, 0);
    setTotalPoints(totalPoints);
  }, [grade]);

  function updatePoints(value: number) {
    setValue(value);

    setGrades((prevGrades) => {
      const updatedGrades = [...prevGrades];
      const gradeIndex = updatedGrades.findIndex(
        (grade) => grade.title === rubric.title
      );

      if (gradeIndex !== -1) {
        updatedGrades[gradeIndex] = {
          ...updatedGrades[gradeIndex],
          points: value,
          comment,
        };
      } else {
        updatedGrades.push({
          title: rubric.title,
          points: value,
          comment,
        });
      }

      const totalPoints = updatedGrades.reduce(
        (sum, grade) => sum + grade.points,
        0
      );
      setTotalPoints(totalPoints);
      return updatedGrades;
    });
  }

  const points = [];
  for (let i = 0; i < rubric.points; i += rubric.points / 4) {
    points.push({ value: i, label: i.toString() });
  }

  return (
    <Stack>
      <Flex>
        <Text>{rubric.title}</Text>
        <Text ml="auto">
          {value}/{rubric.points}
        </Text>
      </Flex>
      <Slider
        size="sm"
        min={0}
        max={rubric.points}
        marks={points}
        value={value}
        onChange={updatePoints}
      >
        {rubric.title}
      </Slider>
      <Textarea
        mt="sm"
        value={comment}
        onChange={(it) => setComment(it.currentTarget.value)}
      ></Textarea>
      <Divider mb="sm" />
    </Stack>
  );
}
