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
} from '@mantine/core';
import React from 'react';

type Props = {
  rubric: Rubric[];
  attachments?: classroom_v1.Schema$Attachment[];
};

interface Grade {
  label: string;
  points: number;
  comment: string;
}

export default function Grader({ rubric, attachments }: Props) {
  const [totalPoints, setTotalPoints] = React.useState(0);
  const [grades, setGrades] = React.useState<Grade[]>([]);

  return (
    <>
      <Card withBorder mb='md' mr='md' shadow='xs'>
        <Flex justify='space-between'>
          <Text fw='bold'>Points</Text>
          <Text fw='bold'>{totalPoints}</Text>
        </Flex>
      </Card>
      <ScrollArea h='90vh' pr='md'>
        {rubric.map((it) => (
          <Item
            grades={grades}
            key={it.title}
            rubric={it}
            setTotalPoints={setTotalPoints}
            setGrades={setGrades}
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
  setGrades: (value: any) => void;
  setTotalPoints: (value: number | ((prevVar: number) => number)) => void;
}) {
  const [value, setValue] = React.useState(0);
  const [comment, setComment] = React.useState('');

  function updatePoints(value: number) {
    setValue(value);
    setGrades((prev: Grade[]) => {
      const index = prev.findIndex((it) => it.label === rubric.title);
      if (index === -1) {
        return [...prev, { label: rubric.title, points: value, comment }];
      } else {
        prev[index].points = value;
        return prev;
      }
    });
    setTotalPoints((prev) => {
      let total = 0;
      for (const it of grades) {
        total += it.points;
      }
      return total;
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
        <Text ml='auto'>
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
      <Textarea mt='sm'></Textarea>
      <Divider mb='sm' />
    </Stack>
  );
}
