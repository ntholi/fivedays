import { classroom_v1 } from 'googleapis';
import {
  Slider,
  Text,
  Stack,
  Divider,
  Textarea,
  ScrollArea,
  Flex,
  Card,
  Button,
} from '@mantine/core';
import React from 'react';
import axios from 'axios';
import { IconCalculator, IconSchoolBell } from '@tabler/icons-react';

type Props = {
  rubric: Rubric[];
  courseWork: classroom_v1.Schema$CourseWork;
  submission: StudentSubmission;
  courseId: string;
};

interface Grade {
  title: string;
  points: number;
  comment: string;
}

export default function Grader({ rubric, courseWork, submission }: Props) {
  const [totalPoints, setTotalPoints] = React.useState(0);
  const [grades, setGrades] = React.useState<Grade[]>([]);
  const [loading, setLoading] = React.useState(false);
  const maxPoints = rubric.reduce((sum, it) => sum + it.points, 0);

  async function doAutoGrading() {
    const fileId = submission?.attachments?.at(0)?.id;
    try {
      setLoading(true);
      const response = await axios.get('/api/grader', {
        params: {
          fileId: fileId,
          questionId: courseWork.id,
          question: courseWork.description,
        },
      });
      console.log(response.data);
      setGrades(response.data);
    } catch (e) {
      console.log(e);
    }
    setLoading(false);
  }

  return (
    <>
      <Flex justify="flex-end" mr="md" mb="sm">
        <Button
          // w="100%"
          leftIcon={<IconCalculator />}
          variant="gradient"
          gradient={{ from: 'indigo', to: 'cyan' }}
          onClick={doAutoGrading}
          loading={loading}
        >
          Auto Grade
        </Button>
      </Flex>
      <Card withBorder mb="md" mr="md" shadow="xs">
        <Flex justify="space-between">
          <Text fw="bold">Points</Text>
          <Text fw="bold">
            {totalPoints}/{maxPoints}
          </Text>
        </Flex>
      </Card>
      <ScrollArea h="90vh" pr="md">
        {rubric.map((it) => (
          <Item
            grades={grades}
            setGrades={setGrades}
            key={it.title}
            rubric={it}
            setTotalPoints={setTotalPoints}
          />
        ))}
      </ScrollArea>
    </>
  );
}

function Item({
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
