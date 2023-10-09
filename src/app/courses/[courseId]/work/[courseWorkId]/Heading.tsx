import { formatDate } from '@/lib/utils/format';
import {
  Container,
  Title,
  Text,
  Divider,
  Flex,
  Button,
  Loader,
} from '@mantine/core';
import { classroom_v1 } from 'googleapis';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function Heading({ courseWork }: Props) {
  return (
    <>
      <Title>{courseWork.title}</Title>
      <div>
        <Text tt='capitalize'>
          {courseWork.workType?.toLowerCase()}
          <Text component={'span'} c='dimmed' size='sm'>
            &nbsp; ({courseWork.maxPoints} Points)
          </Text>
        </Text>
        <Text size='sm' c='dimmed'>
          Due: {formatDate(courseWork.dueDate, courseWork.dueTime)}
        </Text>
        <Text tt='capitalize' size='sm' c='dimmed'>
          {courseWork.state?.toLocaleLowerCase()}
        </Text>
      </div>
    </>
  );
}
