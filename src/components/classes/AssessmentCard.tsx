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
import Link from 'next/link';

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
        <RubricDisplay assessment={courseWork} close={close} />
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
                <Flex justify='space-between' w='100%'>
                  <Button color='dark' onClick={open}>
                    Rubric
                  </Button>
                  <Link
                    href={`/grade/${courseWork.id}?courseId=${courseWork.courseId}`}
                  >
                    <Button>Grade</Button>
                  </Link>
                </Flex>
              </Stack>
            </Accordion.Panel>
          </Accordion.Item>
        </Accordion>
      </Paper>
    </>
  );
}
