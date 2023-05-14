import {
  Accordion,
  Group,
  Button,
  Paper,
  Text,
  Stack,
  Flex,
} from '@mantine/core';
import { IconBook2 } from '@tabler/icons-react';
import axios from 'axios';
import { classroom_v1 } from 'googleapis';
import React from 'react';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function AssessmentCard({ courseWork }: Props) {
  const rubric = async () => {
    const response = await axios.post('/api/rubric/create', { courseWork });
    console.log(response);
  };

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
            <Stack>
              <Text>{courseWork.description}</Text>
              <Flex>
                <Button color='dark' onClick={rubric}>
                  Rubric
                </Button>
              </Flex>
            </Stack>
          </Accordion.Panel>
        </Accordion.Item>
      </Accordion>
    </Paper>
  );
}
