'use client';
import { RootState } from '@/lib/redux/store';
import {
  Accordion,
  Flex,
  Group,
  Slider,
  Stack,
  Text,
  Textarea,
} from '@mantine/core';
import { Criterion } from '@prisma/client';
import axios from 'axios';
import { useSession } from 'next-auth/react';
import React from 'react';
import { useSelector } from 'react-redux';

type Props = {
  criteria: Criterion[] | undefined;
};

type AccordionLabelProps = {
  title: string;
  points: number;
};

function AccordionLabel({ title, points }: AccordionLabelProps) {
  const generateMarks = (points: number) => {
    const step = points / 4;
    const marks = [];
    for (let i = 0; i <= points; i += step) {
      const val = i.toFixed(2);
      marks.push({ value: Number(val), label: String(val) });
    }
    return marks;
  };

  return (
    <Stack h={80} mr={'sm'}>
      <Flex justify='space-between'>
        <Text>{title}</Text>
        <Text size='sm' c='dimmed' fw={400}>
          {points}
        </Text>
      </Flex>
      <Slider
        color='blue'
        size='sm'
        max={points}
        onClick={(e) => e.stopPropagation()}
        marks={generateMarks(points)}
      />
    </Stack>
  );
}

export default function Grader({ criteria }: Props) {
  const session = useSession();
  const state = useSelector(
    (state: RootState) => state.studentSubmission.value
  );

  const items = criteria?.map((it) => (
    <Accordion.Item value={String(it.id)} key={it.title}>
      <Accordion.Control>
        <AccordionLabel {...it} />
      </Accordion.Control>
      <Accordion.Panel>
        <Stack>
          <Textarea description='Comments' />
          <Text size='xs' c='dimmed'>
            {it.description}
          </Text>
        </Stack>
      </Accordion.Panel>
    </Accordion.Item>
  ));
  return (
    <Accordion chevronPosition='right' variant='separated'>
      {items}
    </Accordion>
  );
}

export async function readFile(fileId: string, session: any) {
  const fileUrl = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`;
  const response = await axios.get(fileUrl, {
    headers: { Authorization: `Bearer ${session.accessToken}` },
    responseType: 'text',
  });
  return response.data;
}
