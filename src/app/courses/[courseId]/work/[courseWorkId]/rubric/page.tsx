import React from 'react';
import {
  ActionIcon,
  Button,
  Container,
  Divider,
  Flex,
  List,
} from '@mantine/core';
import RubricForm from './RubricForm';
import prisma from '@/lib/db';
import RubricItem from './RubricItem';
import Heading from '../Heading';
import googleClassroom from '@/lib/config/googleClassroom';
import {
  IconArrowBack,
  IconAtom,
  IconChevronLeft,
  IconWand,
} from '@tabler/icons-react';
import Link from 'next/link';

type Props = {
  params: {
    courseId: string;
    courseWorkId: string;
  };
};
const getCourseWork = async (courseId: string, id: string) => {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.get({
    courseId,
    id,
  });
  return courseWork;
};

export default async function RubricPage({
  params: { courseId, courseWorkId },
}: Props) {
  const courseWork = await getCourseWork(courseId, courseWorkId);
  const rubricItems = await prisma.rubricItem.findMany({
    where: {
      courseId,
      courseWorkId,
    },
    orderBy: {
      createdAt: 'desc',
    },
  });

  return (
    <Container mt='lg' size='md'>
      <Heading courseWork={courseWork} />
      <Flex mt='lg' justify={'space-between'} align='flex-end'>
        <Button
          mt='md'
          variant='light'
          component={Link}
          href={'.'}
          leftSection={<IconChevronLeft />}
        >
          Back
        </Button>
        <ActionIcon
          variant='gradient'
          size='lg'
          aria-label='Generate using AI'
          gradient={{ from: 'blue', to: 'teal', deg: 90 }}
        >
          <IconWand size='1.2rem' />
        </ActionIcon>
      </Flex>
      <Divider my='md' />
      <RubricForm courseId={courseId} courseWorkId={courseWorkId} />

      <form>
        <List mt='xl'>
          {rubricItems.map((it) => (
            <RubricItem key={it.id} rubricItem={it} />
          ))}
        </List>
      </form>
    </Container>
  );
}
