'use client';
import { RootState } from '@/lib/redux/store';
import { Accordion, Group, Text } from '@mantine/core';
import { Rubric, RubricItem } from '@prisma/client';
import axios from 'axios';
import { useSession } from 'next-auth/react';
import React from 'react';
import { useSelector } from 'react-redux';

type Props = {
  criteria: RubricItem[] | undefined;
};

type AccordionLabelProps = {
  title: string;
  points: number;
};

function AccordionLabel({ title, points }: AccordionLabelProps) {
  return (
    <Group wrap='nowrap'>
      <div>
        <Text>{title}</Text>
        <Text size='sm' c='dimmed' fw={400}>
          {points}
        </Text>
      </div>
    </Group>
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
        <Text size='sm'>Comments Here</Text>
      </Accordion.Panel>
    </Accordion.Item>
  ));
  return (
    <Accordion chevronPosition='right' variant='contained'>
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
