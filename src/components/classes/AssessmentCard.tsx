import { Accordion, Group, Paper, Text } from '@mantine/core';
import { IconBook2 } from '@tabler/icons-react';
import { classroom_v1 } from 'googleapis';
import React from 'react';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function AssessmentCard({ courseWork }: Props) {
  return (
    <Paper>
      <Accordion>
        <Accordion.Item value={courseWork.title || ''}>
          <Accordion.Control>
            <Group>
              <IconBook2 />
              <Text fw={500} color=''>
                {courseWork.title}
              </Text>
            </Group>
          </Accordion.Control>
          <Accordion.Panel>
            <Text>{courseWork.description}</Text>
          </Accordion.Panel>
        </Accordion.Item>
      </Accordion>
    </Paper>
  );
}
