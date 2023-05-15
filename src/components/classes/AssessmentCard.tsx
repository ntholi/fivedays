import {
  Accordion,
  Group,
  Button,
  Paper,
  Text,
  Stack,
  Flex,
  Modal,
} from '@mantine/core';
import { IconBook2 } from '@tabler/icons-react';
import axios from 'axios';
import { classroom_v1 } from 'googleapis';
import React, { useState } from 'react';
import RubricDisplay from '../assessments/RubricDisplay';
import { useDisclosure } from '@mantine/hooks';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function AssessmentCard({ courseWork }: Props) {
  const [opened, { open, close }] = useDisclosure(false);

  return (
    <>
      <Modal
        opened={opened}
        onClose={close}
        fullScreen
        transitionProps={{ transition: 'fade', duration: 200 }}
      >
        <RubricDisplay questionId={courseWork.id} />
      </Modal>
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
                  <Button color='dark' onClick={open}>
                    Rubric
                  </Button>
                </Flex>
              </Stack>
            </Accordion.Panel>
          </Accordion.Item>
        </Accordion>
      </Paper>
    </>
  );
}
