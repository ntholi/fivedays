'use client';
import { useDisclosure } from '@mantine/hooks';
import { Modal, Button, Title } from '@mantine/core';
import { classroom_v1 } from 'googleapis';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function RubricModal({ courseWork }: Props) {
  const [opened, { open, close }] = useDisclosure(false);

  return (
    <>
      <Modal opened={opened} onClose={close} title={courseWork.title}>
        <Title>{courseWork.title}</Title>
      </Modal>

      <Button color='gray' onClick={open}>
        Rubric
      </Button>
    </>
  );
}
